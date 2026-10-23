<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierReturnController extends Controller
{
    public function index(Request $request)
    {
        $returns = SupplierReturn::with('supplier', 'store', 'items.product')
            ->when(! $request->user()->isDirection(), fn ($q) => $q->whereIn('store_id', $request->user()->accessibleStoreIds()))
            ->latest('returned_at')->paginate(30)->withQueryString();

        return view('supplier-returns.index', ['returns' => $returns]);
    }

    public function create(Request $request)
    {
        return view('supplier-returns.form', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(['id', 'reference', 'name']),
            'stores' => $this->selectableStores($request),
            'decisions' => config('gesma.supplier_return_decisions'),
        ]);
    }

    public function store(Request $request, PurchaseService $service)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'goods_receipt_id' => ['nullable', 'exists:goods_receipts,id'],
            'returned_at' => ['required', 'date', 'before_or_equal:today'],
            'decision' => ['required', Rule::in(array_keys(config('gesma.supplier_return_decisions')))],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.from_stock' => ['nullable', 'boolean'],
            'items.*.reason' => ['nullable', 'string', 'max:190'],
        ]);
        $data['store_id'] = $this->storeForCreation($request);

        try {
            $return = $service->recordReturn(Supplier::findOrFail($data['supplier_id']), $data, $data['items'], $request->user());
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('supplier-returns.show', $return)->with('success', "Retour {$return->number} déclaré.");
    }

    public function show(Request $request, SupplierReturn $supplierReturn)
    {
        $this->authorizeStore($request, $supplierReturn);
        $supplierReturn->load('supplier', 'store', 'user', 'items.product', 'purchaseOrder', 'goodsReceipt');

        return view('supplier-returns.show', [
            'return' => $supplierReturn,
            'statuses' => config('gesma.supplier_return_statuses'),
        ]);
    }

    public function status(Request $request, SupplierReturn $supplierReturn)
    {
        $this->authorizeStore($request, $supplierReturn);
        $status = $request->validate(['status' => ['required', Rule::in(array_keys(config('gesma.supplier_return_statuses')))]])['status'];
        $supplierReturn->update(['status' => $status]);

        return back()->with('success', 'Statut du retour mis à jour.');
    }
}
