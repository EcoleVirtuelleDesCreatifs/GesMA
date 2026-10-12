<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderSource;
use App\Enums\OrderType;
use App\Enums\SyncStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\Order;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Applique les opérations enregistrées hors connexion.
 * Chaque opération porte un identifiant unique (UUID) généré sur l'appareil :
 * une opération déjà reçue n'est jamais appliquée deux fois.
 */
class SyncService
{
    public const TYPES = ['order.create', 'sale.create', 'expense.create', 'delivery.status', 'customer.create'];

    public function __construct(private OrderService $orders, private DeliveryService $deliveries) {}

    /** @return array<string, mixed> résultat de l'opération pour l'appareil */
    public function process(array $operation, User $user, ?string $deviceId = null): array
    {
        $uuid = $operation['uuid'] ?? null;
        $type = $operation['type'] ?? null;
        $payload = $operation['payload'] ?? [];

        if (! $uuid || ! in_array($type, self::TYPES, true) || ! is_array($payload)) {
            return ['uuid' => $uuid, 'status' => SyncStatus::Failed->value, 'message' => 'Opération invalide.'];
        }

        $existing = SyncOperation::where('uuid', $uuid)->first();
        if ($existing && $existing->status !== SyncStatus::Failed) {
            return $this->response($existing, $existing->status === SyncStatus::Applied ? SyncStatus::Duplicate : null);
        }

        $record = $existing ?? new SyncOperation(['uuid' => $uuid]);
        $record->fill([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'type' => $type,
            'payload' => $payload,
            'client_created_at' => $operation['created_at'] ?? null,
        ]);

        try {
            $result = DB::transaction(fn () => $this->apply($type, $payload, $user, false));
            $record->fill(['status' => SyncStatus::Applied, 'message' => $result['message'], 'result' => $result])->save();
        } catch (InsufficientStockException $e) {
            $record->fill(['status' => SyncStatus::Conflict, 'message' => $e->getMessage(), 'result' => ['shortages' => $e->shortages]])->save();
            AuditLogger::log('sync.conflict', 'Conflit de synchronisation : '.$e->getMessage(), $record, [], $user);
        } catch (SyncConflict $e) {
            $record->fill(['status' => SyncStatus::Conflict, 'message' => $e->getMessage(), 'result' => $e->context])->save();
            AuditLogger::log('sync.conflict', 'Conflit de synchronisation : '.$e->getMessage(), $record, [], $user);
        } catch (ValidationException $e) {
            $record->fill(['status' => SyncStatus::Failed, 'message' => implode(' ', $e->validator->errors()->all()), 'result' => null])->save();
        } catch (BusinessRuleException $e) {
            $record->fill(['status' => SyncStatus::Failed, 'message' => $e->getMessage(), 'result' => null])->save();
        }

        return $this->response($record);
    }

    /** Résolution manuelle d'un conflit : application forcée (stock négatif autorisé) ou rejet. */
    public function resolve(SyncOperation $record, User $user, bool $force): SyncOperation
    {
        if (! in_array($record->status, [SyncStatus::Conflict, SyncStatus::Failed], true)) {
            throw new BusinessRuleException('Cette opération ne nécessite pas de vérification.');
        }

        if ($force) {
            $owner = $record->user ?? $user;
            $result = DB::transaction(fn () => $this->apply($record->type, $record->payload, $owner, true));
            $record->fill(['status' => SyncStatus::Applied, 'message' => 'Appliquée après vérification : '.$result['message'], 'result' => $result]);
        } else {
            $record->fill(['status' => SyncStatus::Resolved, 'message' => 'Rejetée après vérification : '.$record->message]);
        }
        $record->fill(['resolved_at' => now(), 'resolved_by' => $user->id])->save();
        AuditLogger::log('sync.resolve', ($force ? 'Conflit appliqué' : 'Conflit rejeté').' : '.$record->typeLabel(), $record, [], $user);

        return $record;
    }

    private function apply(string $type, array $payload, User $user, bool $force): array
    {
        return match ($type) {
            'order.create', 'sale.create' => $this->applyOrder($type, $payload, $user, $force),
            'expense.create' => $this->applyExpense($payload, $user),
            'delivery.status' => $this->applyDeliveryStatus($payload, $user, $force),
            'customer.create' => $this->applyCustomer($payload, $user),
        };
    }

    private function applyOrder(string $type, array $payload, User $user, bool $force): array
    {
        $data = Validator::make($payload, [
            'uuid' => ['required', 'uuid'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer' => ['nullable', 'array'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'amount_paid' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'requires_delivery' => ['nullable', 'boolean'],
            'delivery' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'ordered_at' => ['nullable', 'date'],
        ])->validate();

        $this->assertStoreAccess($user, (int) $data['store_id']);

        // Doublon : la commande a déjà été créée (par exemple après une réponse perdue).
        if ($order = Order::where('uuid', $data['uuid'])->first()) {
            return ['message' => 'Commande '.$order->number.' déjà enregistrée.', 'order_id' => $order->id, 'number' => $order->number];
        }

        $data['type'] = $type === 'sale.create' ? OrderType::Sale->value : OrderType::Order->value;
        $data['source'] = OrderSource::Offline->value;
        $order = $this->orders->create($data, $user, $force);

        return ['message' => ($order->type === OrderType::Sale ? 'Vente ' : 'Commande ').$order->number.' enregistrée.', 'order_id' => $order->id, 'number' => $order->number];
    }

    private function applyExpense(array $payload, User $user): array
    {
        $data = Validator::make($payload, [
            'uuid' => ['required', 'uuid'],
            'spent_at' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'description' => ['required', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:30'],
        ])->validate();

        $this->assertStoreAccess($user, (int) $data['store_id']);

        $expense = Expense::firstOrCreate(['uuid' => $data['uuid']], $data + ['user_id' => $user->id, 'source' => 'offline']);

        return ['message' => 'Dépense de '.money($expense->amount).' enregistrée.', 'expense_id' => $expense->id];
    }

    private function applyDeliveryStatus(array $payload, User $user, bool $force): array
    {
        $data = Validator::make($payload, [
            'delivery_id' => ['required', 'integer', 'exists:deliveries,id'],
            'status' => ['required', 'in:'.implode(',', DeliveryStatus::values())],
            'expected_status' => ['nullable', 'in:'.implode(',', DeliveryStatus::values())],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        $delivery = Delivery::with('order')->findOrFail($data['delivery_id']);
        $this->assertStoreAccess($user, $delivery->store_id);
        $target = DeliveryStatus::from($data['status']);

        if ($delivery->status === $target) {
            return ['message' => 'Livraison déjà au statut « '.$target->label().' ».', 'delivery_id' => $delivery->id];
        }

        // Le statut a changé sur le serveur pendant la coupure : on demande une vérification.
        if (! $force && ! empty($data['expected_status']) && $delivery->status->value !== $data['expected_status']) {
            throw new SyncConflict(sprintf(
                'La livraison de la commande %s est passée à « %s » pendant la coupure (attendu : « %s »).',
                $delivery->order->number, $delivery->status->label(), DeliveryStatus::from($data['expected_status'])->label()
            ), ['server_status' => $delivery->status->value]);
        }

        $this->deliveries->updateStatus($delivery, $target, $user, $data['reason'] ?? null, $data['notes'] ?? null);

        return ['message' => 'Livraison '.$delivery->order->number.' : '.$target->label().'.', 'delivery_id' => $delivery->id];
    }

    private function applyCustomer(array $payload, User $user): array
    {
        $data = Validator::make($payload, [
            'uuid' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
        ])->validate();

        $customer = $this->orders->resolveCustomer(['customer' => $data], $user->store_id);

        return ['message' => 'Client « '.$customer->name.' » enregistré.', 'customer_id' => $customer->id];
    }

    private function assertStoreAccess(User $user, int $storeId): void
    {
        if (! $user->canAccessStore($storeId)) {
            throw new BusinessRuleException("Vous n'avez pas accès à ce magasin.");
        }
    }

    private function response(SyncOperation $record, ?SyncStatus $override = null): array
    {
        return [
            'uuid' => $record->uuid,
            'status' => ($override ?? $record->status)->value,
            'message' => $record->message,
            'result' => $record->result,
        ];
    }
}
