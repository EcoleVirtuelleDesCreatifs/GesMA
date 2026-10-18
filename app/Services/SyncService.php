<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\Order;
use App\Models\SyncConflict;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Applique les opérations saisies hors connexion (section 11).
 * - Chaque opération porte un identifiant unique (UUID) généré sur l'appareil :
 *   une opération déjà reçue n'est jamais appliquée deux fois (pas de doublon).
 * - Les écarts (stock insuffisant, statut modifié entre-temps) sont enregistrés
 *   comme conflits à vérifier.
 */
class SyncService
{
    public const TYPES = ['order', 'expense', 'delivery_status'];

    public function __construct(private OrderService $orders)
    {
    }

    /** @return array<int, array{uuid: string, status: string, message: string, data?: array}> */
    public function process(array $operations, User $user, ?string $deviceId = null): array
    {
        $results = [];
        foreach ($operations as $op) {
            $results[] = $this->processOne(is_array($op) ? $op : [], $user, $deviceId);
        }

        return $results;
    }

    public function processOne(array $op, User $user, ?string $deviceId): array
    {
        $uuid = (string) ($op['uuid'] ?? '');
        $type = (string) ($op['type'] ?? '');
        $payload = is_array($op['payload'] ?? null) ? $op['payload'] : [];

        if (! preg_match('/^[0-9a-f-]{36}$/i', $uuid) || ! in_array($type, self::TYPES, true)) {
            return ['uuid' => $uuid, 'status' => 'rejetee', 'message' => 'Opération invalide.'];
        }

        // Déjà reçue : on renvoie le même résultat (idempotence)
        if ($existing = SyncOperation::where('uuid', $uuid)->first()) {
            return ['uuid' => $uuid, 'status' => $existing->status, 'message' => $existing->result['message'] ?? 'Déjà synchronisée.', 'data' => $existing->result['data'] ?? [], 'duplicate' => true];
        }

        try {
            [$status, $message, $data, $conflicts] = DB::transaction(fn () => match ($type) {
                'order' => $this->applyOrder($payload, $user, $uuid),
                'expense' => $this->applyExpense($payload, $user, $uuid),
                'delivery_status' => $this->applyDeliveryStatus($payload, $user),
            });
        } catch (ValidationException $e) {
            [$status, $message, $data, $conflicts] = ['rejetee', implode(' ', $e->validator->errors()->all()), [], []];
        } catch (BusinessException $e) {
            [$status, $message, $data, $conflicts] = ['rejetee', $e->getMessage(), [], []];
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Envoi simultané de la même opération depuis deux onglets
            $existing = SyncOperation::where('uuid', $uuid)->first();

            return ['uuid' => $uuid, 'status' => $existing?->status ?? 'appliquee', 'message' => 'Déjà synchronisée.', 'duplicate' => true];
        }

        $operation = SyncOperation::create([
            'uuid' => $uuid,
            'type' => $type,
            'user_id' => $user->id,
            'device_id' => $deviceId ? substr($deviceId, 0, 64) : null,
            'status' => $status,
            'payload' => $payload,
            'result' => ['message' => $message, 'data' => $data],
            'recorded_at' => $this->recordedAt($op),
        ]);

        foreach ($conflicts as $conflict) {
            SyncConflict::create($conflict + ['sync_operation_id' => $operation->id, 'user_id' => $user->id]);
        }

        return ['uuid' => $uuid, 'status' => $status, 'message' => $message, 'data' => $data];
    }

    private function recordedAt(array $op): ?\Illuminate\Support\Carbon
    {
        try {
            return isset($op['recorded_at']) ? \Illuminate\Support\Carbon::parse($op['recorded_at']) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function assertStore(User $user, $storeId): int
    {
        $storeId = (int) $storeId;
        if (! $user->canAccessStore($storeId)) {
            throw new BusinessException('Magasin non autorisé pour cet utilisateur.');
        }

        return $storeId;
    }

    private function applyOrder(array $p, User $user, string $uuid): array
    {
        if (! $user->hasPermission('orders.create')) {
            throw new BusinessException('Vous n’êtes pas autorisé à créer des commandes.');
        }

        // La commande existe déjà (même UUID) : pas de doublon
        if ($order = Order::where('client_uuid', $uuid)->first()) {
            return ['appliquee', "Commande {$order->number} déjà enregistrée.", ['order_id' => $order->id, 'number' => $order->number], []];
        }

        $v = Validator::make($p, [
            'store_id' => ['required', 'integer'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'customer.address' => ['nullable', 'string', 'max:255'],
            'customer.city' => ['nullable', 'string', 'max:100'],
            'customer.client_uuid' => ['nullable', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::in(array_keys(config('gesma.payment_methods')))],
            'direct_sale' => ['nullable', 'boolean'],
            'delivery' => ['nullable', 'array'],
            'delivery.fee' => ['nullable', 'numeric', 'min:0'],
            'delivery.scheduled_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'ordered_at' => ['nullable', 'date'],
        ]);
        $data = $v->validate();
        $data['store_id'] = $this->assertStore($user, $data['store_id']);
        $data['client_uuid'] = $uuid;
        if (isset($data['ordered_at']) && strtotime($data['ordered_at']) > time() + 300) {
            $data['ordered_at'] = now()->toDateTimeString();
        }

        // Produits supprimés entre-temps : ligne retirée et conflit signalé
        $conflicts = [];
        $validIds = \App\Models\Product::whereIn('id', collect($data['items'])->pluck('product_id'))->pluck('id')->all();
        $missing = collect($data['items'])->reject(fn ($i) => in_array((int) $i['product_id'], $validIds, true));
        $data['items'] = collect($data['items'])->filter(fn ($i) => in_array((int) $i['product_id'], $validIds, true))->values()->all();
        if (! $data['items']) {
            throw new BusinessException('Aucun des produits de la commande n’existe plus.');
        }

        // Le prix saisi hors connexion est celui pratiqué auprès du client
        [$order, $shortages] = $this->orders->create($data, $user, 'hors_ligne', allowNegativeStock: true, usePayloadPrices: true);

        if ($missing->isNotEmpty()) {
            $conflicts[] = [
                'store_id' => $order->store_id,
                'type' => 'produit_indisponible',
                'message' => "Commande {$order->number} : produit(s) supprimé(s) retiré(s) de la commande.",
                'details' => ['lignes' => $missing->values()->all()],
                'subject_type' => $order->getMorphClass(),
                'subject_id' => $order->id,
            ];
        }
        if ($shortages) {
            $conflicts[] = [
                'store_id' => $order->store_id,
                'type' => 'stock_insuffisant',
                'message' => "Commande {$order->number} : stock insuffisant lors de la synchronisation.",
                'details' => ['ruptures' => $shortages],
                'subject_type' => $order->getMorphClass(),
                'subject_id' => $order->id,
            ];
        }

        $status = $conflicts ? 'conflit' : 'appliquee';
        $message = $conflicts
            ? "Commande {$order->number} enregistrée, à vérifier : ".implode(' ', array_column($conflicts, 'message'))
            : "Commande {$order->number} enregistrée.";

        return [$status, $message, ['order_id' => $order->id, 'number' => $order->number], $conflicts];
    }

    private function applyExpense(array $p, User $user, string $uuid): array
    {
        if (! $user->hasPermission('expenses.manage')) {
            throw new BusinessException('Vous n’êtes pas autorisé à enregistrer des dépenses.');
        }
        if ($expense = Expense::where('client_uuid', $uuid)->first()) {
            return ['appliquee', 'Dépense déjà enregistrée.', ['expense_id' => $expense->id], []];
        }

        $data = Validator::make($p, [
            'store_id' => ['required', 'integer'],
            'spent_at' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'category' => ['required', Rule::in(array_keys(config('gesma.expense_categories')))],
            'description' => ['nullable', 'string', 'max:255'],
        ])->validate();
        $data['store_id'] = $this->assertStore($user, $data['store_id']);

        $expense = Expense::create($data + ['user_id' => $user->id, 'client_uuid' => $uuid]);

        return ['appliquee', 'Dépense de '.money($expense->amount).' enregistrée.', ['expense_id' => $expense->id], []];
    }

    private function applyDeliveryStatus(array $p, User $user): array
    {
        if (! $user->hasPermission('deliveries.manage')) {
            throw new BusinessException('Vous n’êtes pas autorisé à modifier les livraisons.');
        }

        $data = Validator::make($p, [
            'delivery_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(['en_route', 'livree', 'echec', 'retour'])],
            'reason' => ['nullable', 'string', 'max:255'],
            'expected_status' => ['nullable', 'string'],
        ])->validate();

        $delivery = Delivery::with('order')->find($data['delivery_id']);
        if (! $delivery) {
            throw new BusinessException('Livraison introuvable.');
        }
        $this->assertStore($user, $delivery->store_id);

        if ($delivery->status === $data['status']) {
            return ['appliquee', "Livraison {$delivery->order->number} déjà à jour.", ['delivery_id' => $delivery->id], []];
        }

        $allowed = $this->orders->allowedDeliveryStatuses($delivery);
        $changedMeanwhile = ! empty($data['expected_status']) && $data['expected_status'] !== $delivery->status;

        if (! in_array($data['status'], $allowed, true)) {
            $conflict = [
                'store_id' => $delivery->store_id,
                'type' => 'statut_modifie',
                'message' => sprintf(
                    'Livraison %s : statut « %s » saisi hors connexion non appliqué, la livraison est maintenant « %s ».',
                    $delivery->order->number,
                    config("gesma.delivery_statuses.{$data['status']}"),
                    $delivery->statusLabel()
                ),
                'details' => $data + ['statut_actuel' => $delivery->status],
                'subject_type' => $delivery->order->getMorphClass(),
                'subject_id' => $delivery->order_id,
            ];

            return ['conflit', $conflict['message'], ['delivery_id' => $delivery->id], [$conflict]];
        }

        $reason = $data['reason'] ?? null;
        if (in_array($data['status'], ['echec', 'retour'], true) && blank($reason)) {
            $reason = 'Motif non précisé (saisie hors connexion)';
        }
        $this->orders->changeDeliveryStatus($delivery, $data['status'], $user, $reason);

        $conflicts = [];
        if ($changedMeanwhile) {
            $conflicts[] = [
                'store_id' => $delivery->store_id,
                'type' => 'statut_modifie',
                'message' => "Livraison {$delivery->order->number} : statut modifié par quelqu’un d’autre pendant la coupure, à vérifier.",
                'details' => $data,
                'subject_type' => $delivery->order->getMorphClass(),
                'subject_id' => $delivery->order_id,
            ];
        }

        return [$conflicts ? 'conflit' : 'appliquee', "Livraison {$delivery->order->number} : ".config("gesma.delivery_statuses.{$data['status']}").'.', ['delivery_id' => $delivery->id], $conflicts];
    }
}
