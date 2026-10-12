<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Enums\SyncStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\SyncOperation;
use App\Models\User;
use App\Services\SyncService;
use Illuminate\Http\Request;

/** Fonctionnement hors connexion : données de référence, envoi des opérations en attente, conflits. */
class SyncController extends Controller
{
    public function index()
    {
        $user = $this->user();
        $canResolve = $user->hasPermission('sync.resolve');

        return view('sync.index', [
            'operations' => SyncOperation::with('user')->where('user_id', $user->id)->latest('id')->limit(30)->get(),
            'conflicts' => $canResolve
                ? SyncOperation::with('user.store')->whereIn('status', [SyncStatus::Conflict->value, SyncStatus::Failed->value])
                    ->when(! $user->isDirection(), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('store_id', $user->store_id)))
                    ->latest('id')->get()
                : collect(),
            'canResolve' => $canResolve,
        ]);
    }

    /** Données nécessaires à l'appareil pour travailler sans connexion. */
    public function bootstrap()
    {
        $user = $this->user();
        $stores = $user->accessibleStores();
        $storeIds = $stores->pluck('id');

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'csrf_token' => csrf_token(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'store_id' => $user->store_id,
                'permissions' => [
                    'orders' => $user->hasPermission('orders.manage'),
                    'expenses' => $user->hasPermission('expenses.manage'),
                    'deliveries' => $user->hasPermission('deliveries.manage'),
                    'price' => $user->hasPermission('products.price'),
                ],
            ],
            'stores' => $stores->map->only(['id', 'code', 'name', 'city'])->values(),
            'products' => Product::with('category:id,name')->where('status', '!=', 'discontinued')->orderBy('name')->get()
                ->map(fn ($p) => ['id' => $p->id, 'reference' => $p->reference, 'name' => $p->name, 'price' => $p->price, 'status' => $p->status->value, 'category' => $p->category?->name]),
            'stock' => Stock::whereIn('store_id', $storeIds)->get(['product_id', 'store_id', 'quantity']),
            'customers' => Customer::latest('updated_at')->limit(3000)->get(['id', 'uuid', 'name', 'phone', 'email', 'address', 'city']),
            'expense_categories' => ExpenseCategory::orderBy('name')->get(['id', 'name']),
            'payment_methods' => config('gesma.payment_methods'),
            'drivers' => User::where('role', 'logistics')->where('is_active', true)->whereIn('store_id', $storeIds)->get(['id', 'name', 'store_id']),
            'deliveries' => Delivery::with('order.customer')->whereIn('store_id', $storeIds)
                ->whereIn('status', DeliveryStatus::active())->orderBy('scheduled_date')->limit(500)->get()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'store_id' => $d->store_id,
                    'status' => $d->status->value,
                    'status_label' => $d->status->label(),
                    'order_number' => $d->order->number,
                    'customer' => $d->order->customer?->name,
                    'address' => trim($d->address.($d->city ? ', '.$d->city : '')),
                    'contact_phone' => $d->contact_phone,
                    'scheduled_date' => $d->scheduled_date?->toDateString(),
                    'driver' => $d->driverLabel(),
                    'total' => $d->order->total,
                    'balance' => $d->order->balanceDue(),
                ]),
            'recent_orders' => Order::with('customer:id,name')->whereIn('store_id', $storeIds)
                ->where('ordered_at', '>=', now()->subDays(14))->latest('ordered_at')->limit(300)->get()
                ->map(fn ($o) => [
                    'number' => $o->number,
                    'store_id' => $o->store_id,
                    'customer' => $o->customer?->name,
                    'status' => $o->status->label(),
                    'total' => $o->total,
                    'ordered_at' => $o->ordered_at->toIso8601String(),
                ]),
            'delivery_statuses' => DeliveryStatus::options(),
        ]);
    }

    /** Reçoit les opérations enregistrées hors connexion. */
    public function push(Request $request, SyncService $sync)
    {
        $data = $request->validate([
            'device_id' => ['nullable', 'string', 'max:64'],
            'operations' => ['required', 'array', 'max:'.config('gesma.sync.batch_size')],
            'operations.*.uuid' => ['required', 'uuid'],
            'operations.*.type' => ['required', 'string'],
            'operations.*.payload' => ['required', 'array'],
            'operations.*.created_at' => ['nullable', 'date'],
        ]);

        $results = [];
        foreach ($data['operations'] as $operation) {
            $results[] = $sync->process($operation, $this->user(), $data['device_id'] ?? null);
        }

        return response()->json(['results' => $results, 'synced_at' => now()->toIso8601String()]);
    }

    public function resolve(Request $request, SyncOperation $operation, SyncService $sync)
    {
        $user = $this->user();
        abort_unless($user->isDirection() || $operation->user?->store_id === $user->store_id, 403);
        $data = $request->validate(['action' => ['required', 'in:apply,reject']]);

        $sync->resolve($operation, $user, $data['action'] === 'apply');

        return back()->with('success', $data['action'] === 'apply' ? 'Opération appliquée.' : 'Opération rejetée.');
    }
}
