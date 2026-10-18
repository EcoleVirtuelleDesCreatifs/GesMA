<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\OrderService;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $storeId = $this->storeFilter($request);
        $period = $request->filled('period') ? Period::fromRequest($request) : null;

        $query = Order::visibleTo($user, $storeId)
            ->with('customer', 'store', 'delivery')
            ->search($request->input('q'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->input('source')))
            ->when($period, fn ($q) => $q->between($period->from, $period->to));

        $totals = (clone $query)->toBase()->selectRaw("COUNT(*) as n, SUM(CASE WHEN status NOT IN ('annulee','retournee') THEN total ELSE 0 END) as revenue")->first();

        return view('orders.index', [
            'orders' => $query->latest('ordered_at')->paginate(30)->withQueryString(),
            'stores' => $this->selectableStores($request),
            'storeId' => $storeId,
            'period' => $period,
            'totals' => $totals,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $stores = $this->selectableStores($request);

        return view('orders.create', [
            'stores' => $stores,
            'products' => Product::where('is_available', true)
                ->with(['stocks' => fn ($q) => $q->whereIn('store_id', $stores->pluck('id'))])
                ->orderBy('name')->get(),
            'customer' => $request->filled('customer_id') ? Customer::find($request->integer('customer_id')) : null,
            'drivers' => $this->drivers($user),
            'defaultStore' => $user->store_id ?? $stores->first()?->id,
        ]);
    }

    public function store(Request $request, OrderService $service)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer.name' => ['nullable', 'required_without:customer_id', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'customer.email' => ['nullable', 'email'],
            'customer.address' => ['nullable', 'string', 'max:255'],
            'customer.city' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::in(array_keys(config('gesma.payment_methods')))],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'mode' => ['required', Rule::in(['direct', 'delivery', 'pickup'])],
            'delivery.address' => ['nullable', 'required_if:mode,delivery', 'string', 'max:255'],
            'delivery.city' => ['nullable', 'string', 'max:100'],
            'delivery.contact_phone' => ['nullable', 'string', 'max:30'],
            'delivery.scheduled_date' => ['nullable', 'date'],
            'delivery.driver_id' => ['nullable', 'exists:users,id'],
            'delivery.driver_name' => ['nullable', 'string', 'max:255'],
            'delivery.fee' => ['nullable', 'numeric', 'min:0'],
        ], [
            'customer.name.required_without' => 'Choisissez un client existant ou saisissez le nom d’un nouveau client.',
            'items.required' => 'Ajoutez au moins un produit.',
            'delivery.address.required_if' => 'L’adresse de livraison est obligatoire.',
        ]);

        $data['store_id'] = $this->storeForCreation($request);
        $data['direct_sale'] = $data['mode'] === 'direct';
        if ($data['mode'] !== 'delivery') {
            unset($data['delivery']);
        }
        if (! empty($data['customer_id'])) {
            unset($data['customer']);
        }

        [$order] = $service->create($data, $request->user(), 'gesma');

        return redirect()->route('orders.show', $order)->with('success', $data['direct_sale']
            ? "Vente {$order->number} enregistrée."
            : "Commande {$order->number} créée.");
    }

    public function show(Request $request, Order $order, OrderService $service)
    {
        $this->authorizeStore($request, $order);
        $order->load('items.product', 'customer', 'store', 'user', 'delivery.driver', 'documents', 'statusHistory.user');

        $transitions = collect($order->allowedTransitions())
            ->reject(fn ($s) => $s === 'annulee' && ! $request->user()->hasPermission('orders.cancel'));

        return view('orders.show', [
            'order' => $order,
            'transitions' => $transitions,
        ]);
    }

    public function updateStatus(Request $request, Order $order, OrderService $service)
    {
        $this->authorizeStore($request, $order);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('gesma.order_statuses')))],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        if ($data['status'] === 'annulee') {
            abort_unless($request->user()->hasPermission('orders.cancel'), 403, 'Vous n’êtes pas autorisé à annuler une commande.');
        }

        $service->changeStatus($order, $data['status'], $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Statut mis à jour : '.$order->refresh()->statusLabel().'.');
    }

    public function payment(Request $request, Order $order, OrderService $service)
    {
        $this->authorizeStore($request, $order);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['nullable', Rule::in(array_keys(config('gesma.payment_methods')))],
        ]);
        $service->recordPayment($order, (float) $data['amount'], $data['payment_method'] ?? null);

        return back()->with('success', 'Paiement enregistré.');
    }

    public function invoice(Request $request, Order $order, DocumentService $documents)
    {
        $this->authorizeStore($request, $order);
        $invoice = $documents->invoiceFromOrder($order->load('items'), $request->user());

        return redirect()->route('documents.show', $invoice)->with('success', "Facture {$invoice->number} prête.");
    }

    private function drivers(User $user)
    {
        return User::where('is_active', true)->where('role', 'logistique')
            ->when(! $user->isDirection(), fn ($q) => $q->where('store_id', $user->store_id))
            ->orderBy('name')->get(['id', 'name', 'store_id']);
    }
}
