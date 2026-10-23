<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Services\PurchaseService;
use Illuminate\Http\Request;

class GoodsReceiptController extends Controller
{
    public function index(Request $request)
    {
        $receipts = GoodsReceipt::with('purchaseOrder', 'supplier', 'store', 'user')
            ->when(! $request->user()->isDirection(), fn ($q) => $q->whereIn('store_id', $request->user()->accessibleStoreIds()))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->input('supplier_id')))
            ->latest()->paginate(30)->withQueryString();

        return view('receipts.index', [
            'receipts' => $receipts,
            'openOrders' => PurchaseOrder::open()->whereIn('status', ['validee', 'envoyee', 'partielle'])
                ->when(! $request->user()->isDirection(), fn ($q) => $q->whereIn('store_id', $request->user()->accessibleStoreIds()))
                ->with('supplier')->latest()->get(),
        ]);
    }

    public function create(Request $request, PurchaseOrder $purchase)
    {
        $this->authorizeStore($request, $purchase);
        abort_unless(in_array($purchase->status, ['validee', 'envoyee', 'partielle'], true), 422, 'Cette commande ne peut pas être réceptionnée.');
        $purchase->load('items.product', 'supplier');

        return view('receipts.form', [
            'order' => $purchase,
            'stores' => $this->selectableStores($request),
        ]);
    }

    public function store(Request $request, PurchaseOrder $purchase, PurchaseService $service)
    {
        $this->authorizeStore($request, $purchase);
        abort_unless($request->user()->hasPermission('receipts.manage'), 403);

        $request->validate([
            'store_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
            'items' => ['array'],
            'items.*.received' => ['nullable', 'integer', 'min:0'],
            'items.*.rejected' => ['nullable', 'integer', 'min:0'],
            'items.*.defect_reason' => ['nullable', 'string', 'max:190'],
        ]);

        // Le magasin destinataire : celui choisi s'il est accessible, sinon celui de la commande
        $storeId = $request->user()->isDirection() && $request->filled('store_id')
            ? (int) $request->input('store_id')
            : (int) $purchase->store_id;
        abort_unless($request->user()->canAccessStore($storeId), 403, 'Magasin non autorisé.');

        try {
            $receipt = $service->recordReceipt($purchase, $request->input('items', []), $storeId, $request->input('notes'), $request->user());
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('purchases.show', $purchase)->with('success', "Réception {$receipt->number} validée : le stock a été mis à jour.");
    }

    public function show(Request $request, GoodsReceipt $receipt)
    {
        $this->authorizeStore($request, $receipt);
        $receipt->load('purchaseOrder', 'supplier', 'store', 'user', 'items.product', 'items.purchaseOrderItem');

        return view('receipts.show', ['receipt' => $receipt]);
    }
}
