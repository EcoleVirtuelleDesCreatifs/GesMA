<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseOrder::with('supplier', 'store')
            ->when($request->filled('q'), fn ($q) => $q->where('number', 'like', '%'.$request->input('q').'%')
                ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%'.$request->input('q').'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->input('supplier_id')))
            ->when(! $request->user()->isDirection(), fn ($q) => $q->whereIn('store_id', $request->user()->accessibleStoreIds()))
            ->latest();

        return view('purchases.index', [
            'orders' => $query->paginate(30)->withQueryString(),
            'statuses' => config('gesma.purchase_statuses'),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('purchases.form', [
            'order' => new PurchaseOrder(['ordered_at' => today()]),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::where('is_available', true)->orderBy('name')->get(['id', 'reference', 'name', 'price']),
            'stores' => $this->selectableStores($request),
        ]);
    }

    public function store(Request $request, PurchaseService $service)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'ordered_at' => ['required', 'date'],
            'expected_at' => ['nullable', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'extra_fees' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);
        $data['store_id'] = $this->storeForCreation($request);

        $order = $service->createOrder($data, $request->user());

        return redirect()->route('purchases.show', $order)->with('success', "Commande {$order->number} créée (brouillon).");
    }

    public function show(Request $request, PurchaseOrder $purchase)
    {
        $purchase->load('supplier', 'store', 'user', 'items.product', 'receipts.items.product', 'invoices.payments');

        return view('purchases.show', [
            'order' => $purchase,
            'stores' => $this->selectableStores($request),
            'transitions' => config("gesma.purchase_transitions.{$purchase->status}", []),
        ]);
    }

    public function status(Request $request, PurchaseOrder $purchase, PurchaseService $service)
    {
        $status = $request->validate(['status' => ['required', 'string']])['status'];
        // La validation est réservée à la Direction
        if ($status === 'validee') {
            abort_unless($request->user()->hasPermission('purchases.validate'), 403, 'Seule la Direction peut valider une commande.');
        }
        abort_unless($request->user()->hasPermission('purchases.manage'), 403);
        $this->authorizeStore($request, $purchase);

        try {
            $service->changeStatus($purchase, $status, $request->user());
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Statut mis à jour : '.$purchase->fresh()->statusLabel());
    }

    /** Bon de commande PDF (papier à en-tête MOBILIER GROUP'S) */
    public function pdf(Request $request, PurchaseOrder $purchase)
    {
        $this->authorizeStore($request, $purchase);
        $purchase->load('supplier', 'store', 'items.product');

        return Pdf::loadView('pdf.purchase-order', ['order' => $purchase])
            ->setPaper('a4')
            ->download($purchase->number.'.pdf');
    }
}
