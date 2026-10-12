<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->user();
        $query = Order::with(['customer', 'store', 'delivery'])->visibleTo($user)
            ->forStore($this->selectedStoreId($request))
            ->search($request->input('q'))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->input('source'), fn ($q, $s) => $q->where('source', $s));

        if ($request->input('period') || $request->filled('from')) {
            $period = Period::fromRequest($request);
            $query->between($period->from, $period->to);
        }

        return view('orders.index', [
            'orders' => $query->latest('ordered_at')->paginate(25)->withQueryString(),
            'stores' => $user->accessibleStores(),
        ]);
    }

    public function create(Request $request)
    {
        $customer = $request->integer('customer_id') ? Customer::find($request->integer('customer_id')) : null;

        return view('orders.create', [
            'stores' => $this->user()->accessibleStores(),
            'products' => Product::with('stocks')->where('status', '!=', 'discontinued')->orderBy('name')->get(),
            'customer' => $customer,
            'drivers' => User::where('role', 'logistics')->where('is_active', true)->orderBy('name')->get(),
            'type' => $request->input('type', 'order'),
        ]);
    }

    public function store(Request $request, OrderService $orders)
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(OrderType::class)],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer.name' => [Rule::requiredIf(fn () => $request->input('type') === OrderType::Order->value && ! $request->filled('customer_id')), 'nullable', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.address' => ['nullable', 'string', 'max:255'],
            'customer.city' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'amount_paid' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['nullable', Rule::in(array_keys(config('gesma.payment_methods')))],
            'requires_delivery' => ['nullable', 'boolean'],
            'delivery.address' => ['nullable', 'required_if:requires_delivery,1', 'string', 'max:255'],
            'delivery.city' => ['nullable', 'string', 'max:100'],
            'delivery.contact_name' => ['nullable', 'string', 'max:255'],
            'delivery.contact_phone' => ['nullable', 'string', 'max:30'],
            'delivery.scheduled_date' => ['nullable', 'date'],
            'delivery.driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'delivery.driver_name' => ['nullable', 'string', 'max:255'],
            'delivery.fee' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['store_id'] = $this->storeIdForCreation($request);
        $data['source'] = OrderSource::Gesma->value;
        $data['items'] = array_values(array_filter($data['items'], fn ($i) => ! empty($i['product_id'])));
        if (empty($data['customer_id']) && empty($data['customer']['name'])) {
            unset($data['customer']);
        }

        $order = $orders->create($data, $this->user());

        return redirect()->route('orders.show', $order)->with('success', ($order->type === OrderType::Sale ? 'Vente ' : 'Commande ').$order->number.' enregistrée.');
    }

    public function show(Order $order)
    {
        $this->authorizeStore($order);
        $order->load(['items.product', 'customer', 'store', 'user', 'delivery.driver', 'history.user', 'invoices']);

        return view('orders.show', [
            'order' => $order,
            'drivers' => User::where('role', 'logistics')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function updateStatus(Request $request, Order $order, OrderService $orders)
    {
        $this->authorizeStore($order);
        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $status = OrderStatus::from($data['status']);
        $status === OrderStatus::Cancelled
            ? $this->authorizeAny('orders.cancel')
            : $this->authorizeAny('orders.manage', 'deliveries.manage');

        $orders->changeStatus($order, $status, $this->user(), $data['reason'] ?? null);

        return back()->with('success', 'Statut mis à jour : '.$status->label().'.');
    }

    public function payment(Request $request, Order $order)
    {
        $this->authorizeStore($order);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['nullable', Rule::in(array_keys(config('gesma.payment_methods')))],
        ]);
        $order->update([
            'amount_paid' => min($order->total, $order->amount_paid + $data['amount']),
            'payment_method' => $data['payment_method'] ?? $order->payment_method,
        ]);

        return back()->with('success', 'Paiement de '.money($data['amount']).' enregistré.');
    }

    /** Ajoute une livraison à une commande qui n'en avait pas. */
    public function addDelivery(Request $request, Order $order)
    {
        $this->authorizeStore($order);
        abort_if($order->delivery || ! $order->status->holdsStock() || $order->status === OrderStatus::Delivered, 422, 'Livraison impossible pour cette commande.');
        $data = $request->validate([
            'address' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'scheduled_date' => ['nullable', 'date'],
            'driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'fee' => ['nullable', 'integer', 'min:0'],
        ]);
        $fee = (int) ($data['fee'] ?? 0);
        $order->delivery()->create($data + ['store_id' => $order->store_id, 'status' => DeliveryStatus::Pending, 'fee' => $fee]);
        $order->update([
            'requires_delivery' => true,
            'delivery_address' => $data['address'],
            'delivery_city' => $data['city'] ?? null,
            'delivery_fee' => $fee,
            'total' => max(0, $order->subtotal - $order->discount + $fee),
        ]);

        return back()->with('success', 'Livraison programmée.');
    }
}
