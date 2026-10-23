<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupplierInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = SupplierInvoice::with('supplier', 'purchaseOrder')
            ->when($request->filled('q'), fn ($q) => $q->where('number', 'like', '%'.$request->input('q').'%')
                ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%'.$request->input('q').'%')))
            ->when($request->input('status') === 'non_payee', fn ($q) => $q->unpaid())
            ->when($request->input('status') === 'en_retard', fn ($q) => $q->overdue())
            ->latest('invoiced_at')->paginate(30)->withQueryString();

        return view('supplier-invoices.index', [
            'invoices' => $invoices,
            'totalDebt' => (float) SupplierInvoice::unpaid()->get()->sum->balance(),
            'overdueCount' => SupplierInvoice::overdue()->count(),
        ]);
    }

    public function create(Request $request)
    {
        return view('supplier-invoices.form', [
            'invoice' => new SupplierInvoice(['invoiced_at' => today()]),
            'suppliers' => Supplier::orderBy('name')->get(),
            'orders' => PurchaseOrder::whereNotIn('status', ['brouillon', 'annulee'])->with('supplier')->latest()->limit(100)->get(),
            'stores' => $this->selectableStores($request),
        ]);
    }

    public function store(Request $request, PurchaseService $service)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'number' => ['required', 'string', 'max:60', Rule::unique('supplier_invoices')->where('supplier_id', $request->input('supplier_id'))],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'invoiced_at' => ['required', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:invoiced_at'],
            'total' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:8192', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('supplier-invoices');
        }
        $data['store_id'] = $request->filled('purchase_order_id')
            ? PurchaseOrder::find($data['purchase_order_id'])?->store_id
            : ($request->user()->isDirection() ? $request->input('store_id') : $request->user()->store_id);

        $invoice = $service->createInvoice($data, $request->user());

        return redirect()->route('supplier-invoices.show', $invoice)->with('success', 'Facture fournisseur enregistrée.');
    }

    public function show(SupplierInvoice $supplierInvoice)
    {
        $supplierInvoice->load('supplier', 'purchaseOrder', 'payments.user', 'payments.expense');

        return view('supplier-invoices.show', [
            'invoice' => $supplierInvoice,
            'methods' => array_intersect_key(config('gesma.payment_methods'), array_flip(['especes', 'virement', 'mobile_money', 'cheque'])),
            'paid' => $supplierInvoice->paid(),
            'balance' => $supplierInvoice->balance(),
        ]);
    }

    public function attachment(SupplierInvoice $supplierInvoice)
    {
        abort_unless($supplierInvoice->attachment, 404);

        return Storage::download($supplierInvoice->attachment, 'facture-'.$supplierInvoice->number.'.'.pathinfo($supplierInvoice->attachment, PATHINFO_EXTENSION));
    }

    public function storePayment(Request $request, SupplierInvoice $supplierInvoice, PurchaseService $service)
    {
        $data = $request->validate([
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', Rule::in(['especes', 'virement', 'mobile_money', 'cheque'])],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:8192', 'mimes:pdf,jpg,jpeg,png'],
        ]);
        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('supplier-payments');
        }

        try {
            $payment = $service->recordPayment($supplierInvoice, $data, $request->user());
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $balance = $supplierInvoice->fresh()->balance();

        return back()->with('success', sprintf('Règlement de %s enregistré. Solde restant : %s', money($payment->amount), money($balance)));
    }
}
