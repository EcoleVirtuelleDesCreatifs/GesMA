<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $storeId = $this->storeFilter($request);
        $view = $request->input('view', 'a_traiter');

        $deliveries = Delivery::visibleTo($user, $storeId)
            ->with('order.customer', 'driver', 'store')
            ->when($view === 'a_traiter', fn ($q) => $q->whereIn('status', ['preparation', 'en_route', 'echec']))
            ->when($view === 'aujourdhui', fn ($q) => $q->whereDate('scheduled_date', today()))
            ->when($view === 'terminees', fn ($q) => $q->whereIn('status', ['livree', 'retour', 'annulee']))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('driver_id'), fn ($q) => $q->where('driver_id', $request->integer('driver_id')))
            ->when($request->boolean('mine'), fn ($q) => $q->where('driver_id', $user->id))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('order', fn ($o) => $o->search($request->input('q'))))
            ->orderByRaw('scheduled_date IS NULL')
            ->orderBy('scheduled_date')
            ->latest('id')
            ->paginate(30)->withQueryString();

        $counts = Delivery::visibleTo($user, $storeId)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('deliveries.index', [
            'deliveries' => $deliveries,
            'counts' => $counts,
            'view' => $view,
            'stores' => $this->selectableStores($request),
            'storeId' => $storeId,
            'drivers' => $this->drivers($user),
        ]);
    }

    public function edit(Request $request, Delivery $delivery, OrderService $service)
    {
        $this->authorizeStore($request, $delivery);
        $delivery->load('order.customer', 'order.items', 'driver');

        $allowed = collect($service->allowedDeliveryStatuses($delivery))
            ->reject(fn ($s) => $s === 'annulee' && ! $request->user()->hasPermission('orders.cancel'));

        return view('deliveries.edit', [
            'delivery' => $delivery,
            'allowedStatuses' => $allowed,
            'drivers' => $this->drivers($request->user()),
        ]);
    }

    public function update(Request $request, Delivery $delivery)
    {
        $this->authorizeStore($request, $delivery);
        $data = $request->validate([
            'address' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'scheduled_date' => ['nullable', 'date'],
            'driver_id' => ['nullable', 'exists:users,id'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['fee'] = (float) ($data['fee'] ?? 0);

        $delivery->update($data);

        // Les frais de livraison sont répercutés sur le total de la commande
        $order = $delivery->order;
        if ((float) $order->delivery_fee !== $data['fee'] && ! in_array($order->status, ['annulee', 'retournee'], true)) {
            $order->delivery_fee = $data['fee'];
            $order->total = (float) $order->subtotal - (float) $order->discount + $data['fee'];
            $order->save();
        }

        return redirect()->route('deliveries.edit', $delivery)->with('success', 'Livraison mise à jour.');
    }

    public function updateStatus(Request $request, Delivery $delivery, OrderService $service)
    {
        $this->authorizeStore($request, $delivery);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('gesma.delivery_statuses')))],
            'reason' => [Rule::requiredIf(fn () => in_array($request->input('status'), ['echec', 'retour', 'annulee'], true)), 'nullable', 'string', 'max:255'],
        ], ['reason.required' => 'Indiquez le motif de l’échec, du retour ou de l’annulation.']);

        if ($data['status'] === 'annulee') {
            abort_unless($request->user()->hasPermission('orders.cancel'), 403);
        }

        $service->changeDeliveryStatus($delivery->load('order.items'), $data['status'], $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Livraison : '.$delivery->refresh()->statusLabel().'.');
    }

    private function drivers(User $user)
    {
        return User::where('is_active', true)->whereIn('role', ['logistique', 'responsable'])
            ->when(! $user->isDirection(), fn ($q) => $q->where('store_id', $user->store_id))
            ->orderBy('name')->get(['id', 'name']);
    }
}
