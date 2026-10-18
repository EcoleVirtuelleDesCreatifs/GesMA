<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Product;
use App\Models\SyncOperation;
use App\Models\Store;
use App\Services\SyncService;
use Illuminate\Http\Request;

/** Mode hors connexion : données locales et synchronisation (section 11) */
class OfflineController extends Controller
{
    /** Page de saisie hors connexion (mise en cache par le navigateur) */
    public function index()
    {
        return view('offline.index');
    }

    /** Instantané des données nécessaires hors connexion pour l'utilisateur */
    public function data(Request $request)
    {
        $user = $request->user();
        $storeIds = $user->accessibleStoreIds();

        $products = Product::where('is_available', true)
            ->with(['category:id,name', 'stocks' => fn ($q) => $q->whereIn('store_id', $storeIds)])
            ->orderBy('name')->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'reference' => $p->reference,
                'category' => $p->category?->name,
                'price' => (float) $p->price,
                'stocks' => $p->stocks->pluck('quantity', 'store_id'),
            ]);

        $customers = $user->hasPermission('orders.create')
            ? Customer::visibleTo($user)->latest('updated_at')->limit(config('gesma.offline_customer_limit', 1000))
                ->get(['id', 'name', 'phone', 'address', 'city'])
            : collect();

        $deliveries = $user->hasPermission('deliveries.view')
            ? Delivery::whereIn('store_id', $storeIds)->whereIn('status', ['preparation', 'en_route', 'echec'])
                ->with('order:id,number,total,amount_paid,customer_id', 'order.customer:id,name')
                ->orderBy('scheduled_date')->limit(500)->get()
                ->map(fn (Delivery $d) => [
                    'id' => $d->id,
                    'store_id' => $d->store_id,
                    'order_number' => $d->order->number,
                    'customer' => $d->order->customer?->name ?? $d->contact_name,
                    'address' => trim($d->address.' '.$d->city),
                    'phone' => $d->contact_phone,
                    'status' => $d->status,
                    'scheduled_date' => $d->scheduled_date?->format('d/m/Y'),
                    'driver' => $d->driverLabel(),
                    'balance' => $d->order->balance(),
                ])
            : collect();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'csrf_token' => csrf_token(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'role_label' => $user->roleLabel(),
                'store_id' => $user->store_id,
                'can' => [
                    'orders' => $user->hasPermission('orders.create'),
                    'expenses' => $user->hasPermission('expenses.manage'),
                    'deliveries' => $user->hasPermission('deliveries.manage'),
                ],
            ],
            'stores' => Store::whereIn('id', $storeIds)->active()->orderBy('name')->get(['id', 'name', 'city']),
            'products' => $products,
            'customers' => $customers,
            'deliveries' => $deliveries,
            'payment_methods' => config('gesma.payment_methods'),
            'expense_categories' => config('gesma.expense_categories'),
            'delivery_statuses' => config('gesma.delivery_statuses'),
            'currency' => config('gesma.company.currency'),
        ]);
    }

    /** Reçoit la file d'opérations d'un appareil */
    public function sync(Request $request, SyncService $sync)
    {
        $request->validate([
            'device_id' => ['nullable', 'string', 'max:64'],
            'operations' => ['required', 'array', 'max:200'],
        ]);

        $results = $sync->process($request->input('operations'), $request->user(), $request->input('device_id'));

        return response()->json([
            'synced_at' => now()->toIso8601String(),
            'results' => $results,
        ]);
    }

    /** Historique des synchronisations de l'utilisateur */
    public function history(Request $request)
    {
        $operations = SyncOperation::where('user_id', $request->user()->id)
            ->latest()->paginate(30);

        return view('offline.history', compact('operations'));
    }
}
