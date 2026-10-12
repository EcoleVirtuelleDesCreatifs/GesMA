<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\User;
use App\Services\DeliveryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->user();
        $view = $request->input('view', 'active');
        $deliveries = Delivery::with(['order.customer', 'store', 'driver'])
            ->visibleTo($user)
            ->forStore($this->selectedStoreId($request))
            ->when($view === 'active', fn ($q) => $q->whereIn('status', DeliveryStatus::active()))
            ->when($view === 'today', fn ($q) => $q->whereDate('scheduled_date', today()))
            ->when($view === 'mine', fn ($q) => $q->where('driver_id', $user->id))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('q'), fn ($q, $term) => $q->whereHas('order', fn ($o) => $o->search($term)))
            ->orderByRaw('scheduled_date IS NULL, scheduled_date')
            ->latest('id')
            ->paginate(25)->withQueryString();

        $counts = Delivery::visibleTo($user)->forStore($this->selectedStoreId($request))
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('deliveries.index', [
            'deliveries' => $deliveries,
            'counts' => $counts,
            'view' => $view,
            'stores' => $user->accessibleStores(),
            'storeId' => $this->selectedStoreId($request),
        ]);
    }

    public function show(Delivery $delivery)
    {
        $this->authorizeStore($delivery);

        return view('deliveries.show', [
            'delivery' => $delivery->load(['order.items', 'order.customer', 'order.history.user', 'store', 'driver']),
            'drivers' => User::where('role', 'logistics')->where('is_active', true)
                ->where(fn ($q) => $q->where('store_id', $delivery->store_id)->orWhereNull('store_id'))->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Delivery $delivery)
    {
        $this->authorizeStore($delivery);
        $data = $request->validate([
            'address' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'scheduled_date' => ['nullable', 'date'],
            'driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'fee' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['fee'] = (int) ($data['fee'] ?? 0);
        $delivery->update($data);

        $order = $delivery->order;
        if ($order->delivery_fee !== $data['fee']) {
            $order->update(['delivery_fee' => $data['fee'], 'total' => max(0, $order->subtotal - $order->discount + $data['fee'])]);
        }

        return back()->with('success', 'Livraison mise à jour.');
    }

    public function updateStatus(Request $request, Delivery $delivery, DeliveryService $service)
    {
        $this->authorizeStore($delivery);
        $data = $request->validate([
            'status' => ['required', Rule::enum(DeliveryStatus::class)],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $status = DeliveryStatus::from($data['status']);
        $service->updateStatus($delivery, $status, $this->user(), $data['reason'] ?? null, $data['notes'] ?? null);

        return back()->with('success', 'Livraison : '.$status->label().'.');
    }
}
