<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\StatsService;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $sort = $request->input('sort', 'recent');
        $customers = Customer::query()->withPurchaseStats()
            ->search($request->input('q'))
            ->when($request->input('city'), fn ($q, $c) => $q->where('city', $c))
            ->when($sort === 'orders', fn ($q) => $q->orderByDesc('orders_count'))
            ->when($sort === 'spent', fn ($q) => $q->orderByDesc('total_spent'))
            ->when($sort === 'name', fn ($q) => $q->orderBy('name'))
            ->when($sort === 'recent', fn ($q) => $q->latest())
            ->paginate(25)->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'cities' => Customer::whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
        ]);
    }

    /** Clients qui commandent le plus souvent / réalisent les achats les plus importants. */
    public function top(Request $request, StatsService $stats)
    {
        $user = $this->user();
        $period = Period::fromRequest($request, 'year');
        $storeIds = $stats->storeIds($user, $this->selectedStoreId($request));
        $byAmount = $stats->topCustomers($storeIds, $period, 20);

        $byFrequency = $stats->orders($storeIds, $period)->valid()->whereNotNull('customer_id')
            ->selectRaw('customer_id, COUNT(*) as orders_count, SUM(total) as total_spent')
            ->groupBy('customer_id')->orderByDesc('orders_count')->limit(20)->get();
        $customers = Customer::whereIn('id', $byFrequency->pluck('customer_id'))->get()->keyBy('id');
        $byFrequency->each(fn ($r) => $r->customer = $customers[$r->customer_id] ?? null);

        return view('customers.top', [
            'period' => $period,
            'byAmount' => $byAmount,
            'byFrequency' => $byFrequency->filter(fn ($r) => $r->customer),
            'stores' => $user->accessibleStores(),
            'storeId' => $this->selectedStoreId($request),
        ]);
    }

    /** Recherche rapide (formulaires de commande, devis, factures). */
    public function search(Request $request)
    {
        return Customer::search($request->input('q'))->orderBy('name')->limit(15)
            ->get(['id', 'name', 'phone', 'email', 'address', 'city']);
    }

    public function create()
    {
        return view('customers.form', ['customer' => new Customer]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['store_id'] = $this->user()->store_id;
        $customer = Customer::create($data);

        if ($request->input('redirect') === 'order') {
            return redirect()->route('orders.create', ['customer_id' => $customer->id]);
        }

        return redirect()->route('customers.show', $customer)->with('success', 'Client enregistré.');
    }

    public function show(Customer $customer)
    {
        $user = $this->user();
        $customer->loadSum(['orders as total_spent' => fn ($q) => $q->valid()], 'total');

        return view('customers.show', [
            'customer' => $customer,
            'orders' => $customer->orders()->visibleTo($user)->with('store')->latest('ordered_at')->limit(50)->get(),
            'quotes' => $customer->quotes()->visibleTo($user)->latest('issued_at')->limit(30)->get(),
            'invoices' => $customer->invoices()->visibleTo($user)->latest('issued_at')->limit(30)->get(),
            'deliveries' => $customer->deliveries()->with('order')->when(! $user->isDirection(), fn ($q) => $q->where('deliveries.store_id', $user->store_id))->latest('deliveries.created_at')->limit(30)->get(),
        ]);
    }

    public function edit(Customer $customer)
    {
        return view('customers.form', ['customer' => $customer]);
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request, $customer));

        return redirect()->route('customers.show', $customer)->with('success', 'Fiche client mise à jour.');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'phone_alt' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['phone'] = Customer::normalizePhone($data['phone'] ?? null);
        $data['phone_alt'] = Customer::normalizePhone($data['phone_alt'] ?? null);

        $duplicate = $data['phone'] && Customer::where('phone', $data['phone'])
            ->when($customer, fn ($q) => $q->whereKeyNot($customer->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['phone' => 'Un client avec ce numéro de téléphone existe déjà.']);
        }

        return $data;
    }
}
