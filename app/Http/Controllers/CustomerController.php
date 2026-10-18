<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\StatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request, StatsService $stats)
    {
        $user = $request->user();
        $storeId = $this->storeFilter($request);
        $sort = $request->input('sort', 'name');

        $orderStats = Order::countedAsSale()
            ->when(! $user->isDirection() || $storeId, fn ($q) => $q->where('store_id', $storeId ?? $user->store_id))
            ->select('customer_id', DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(total) as amount'), DB::raw('MAX(ordered_at) as last_order'))
            ->groupBy('customer_id');

        $customers = Customer::visibleTo($user, $storeId)
            ->leftJoinSub($orderStats, 'stats', 'stats.customer_id', '=', 'customers.id')
            ->select('customers.*', DB::raw('COALESCE(stats.orders_count, 0) as orders_count'), DB::raw('COALESCE(stats.amount, 0) as amount'), 'stats.last_order')
            ->search($request->input('q'))
            ->when($request->filled('city'), fn ($q) => $q->where('customers.city', $request->input('city')))
            ->when($sort === 'frequency', fn ($q) => $q->orderByDesc('orders_count'))
            ->when($sort === 'amount', fn ($q) => $q->orderByDesc('amount'))
            ->when($sort === 'recent', fn ($q) => $q->orderByDesc('stats.last_order'))
            ->orderBy('customers.name')
            ->paginate(30)->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'stores' => $this->selectableStores($request),
            'storeId' => $storeId,
            'cities' => Customer::whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'topByCount' => $stats->topCustomers($user, $storeId, null, 5, 'count'),
            'topByAmount' => $stats->topCustomers($user, $storeId, null, 5, 'amount'),
        ]);
    }

    /** Recherche rapide (formulaires de commande, devis) */
    public function search(Request $request)
    {
        $customers = Customer::visibleTo($request->user())
            ->search($request->input('q'))
            ->orderBy('name')->limit(15)
            ->get(['id', 'name', 'phone', 'email', 'address', 'city']);

        return response()->json($customers);
    }

    public function create(Request $request)
    {
        return view('customers.form', ['customer' => new Customer(), 'stores' => $this->selectableStores($request)]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['store_id'] = $this->storeForCreation($request);
        $customer = Customer::create($data);

        if ($request->input('redirect') === 'order') {
            return redirect()->route('orders.create', ['customer_id' => $customer->id])->with('success', 'Client créé.');
        }

        return redirect()->route('customers.show', $customer)->with('success', 'Client créé.');
    }

    public function show(Request $request, Customer $customer)
    {
        $user = $request->user();
        abort_unless(Customer::visibleTo($user)->whereKey($customer->id)->exists(), 403);

        $orders = $customer->orders()->visibleTo($user)->with('store', 'delivery')->latest('ordered_at')->get();
        $documents = $customer->documents()->visibleTo($user)->latest('issued_at')->get();
        $deliveries = Delivery::whereIn('order_id', $orders->pluck('id'))->with('order')->latest()->get();
        $counted = $orders->whereNotIn('status', Order::EXCLUDED_FROM_SALES);

        return view('customers.show', [
            'customer' => $customer,
            'orders' => $orders,
            'documents' => $documents,
            'deliveries' => $deliveries,
            'summary' => [
                'orders' => $counted->count(),
                'amount' => $counted->sum('total'),
                'balance' => $counted->sum(fn ($o) => $o->balance()),
                'cancelled' => $orders->where('status', 'annulee')->count(),
                'returned' => $orders->where('status', 'retournee')->count(),
            ],
        ]);
    }

    public function edit(Request $request, Customer $customer)
    {
        abort_unless(Customer::visibleTo($request->user())->whereKey($customer->id)->exists(), 403);

        return view('customers.form', ['customer' => $customer, 'stores' => $this->selectableStores($request)]);
    }

    public function update(Request $request, Customer $customer)
    {
        abort_unless(Customer::visibleTo($request->user())->whereKey($customer->id)->exists(), 403);
        $customer->update($this->validated($request, $customer));

        return redirect()->route('customers.show', $customer)->with('success', 'Fiche client mise à jour.');
    }

    public function destroy(Request $request, Customer $customer)
    {
        abort_unless(Customer::visibleTo($request->user())->whereKey($customer->id)->exists(), 403);
        if ($customer->documents()->exists()) {
            return back()->withErrors(['business' => "Suppression impossible : {$customer->name} a des devis ou factures."]);
        }
        $ordersCount = $customer->orders()->count();
        ActivityLog::record(
            'client.suppression',
            "Suppression du client « {$customer->name} »".($ordersCount ? " ({$ordersCount} commande(s) conservée(s))" : ''),
            null,
            $customer->only('id', 'name', 'phone', 'email', 'city', 'store_id'),
            $customer->store_id
        );
        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Client supprimé.');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('customers')->ignore($customer)],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['phone.unique' => 'Un client avec ce numéro existe déjà.']);
    }
}
