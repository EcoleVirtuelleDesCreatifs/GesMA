<?php

namespace App\Http\Controllers;

use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierPaymentController extends Controller
{
    /** Paiements et dettes : règlements récents + soldes par fournisseur */
    public function index(Request $request)
    {
        $payments = SupplierPayment::with('invoice', 'supplier', 'user', 'store')
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->input('supplier_id')))
            ->latest('paid_at')->paginate(30)->withQueryString();

        $debts = DB::table('supplier_invoices')
            ->join('suppliers', 'suppliers.id', '=', 'supplier_invoices.supplier_id')
            ->selectRaw('supplier_invoices.supplier_id, suppliers.name, suppliers.code,
                SUM(supplier_invoices.total) as facture,
                COALESCE((SELECT SUM(amount) FROM supplier_payments WHERE supplier_payments.supplier_id = supplier_invoices.supplier_id),0) as paye')
            ->groupBy('supplier_invoices.supplier_id', 'suppliers.name', 'suppliers.code')
            ->get()->map(fn ($r) => (object) [
                'supplier_id' => $r->supplier_id, 'name' => $r->name, 'code' => $r->code,
                'facture' => (float) $r->facture, 'paye' => (float) $r->paye,
                'dette' => max(0, (float) $r->facture - (float) $r->paye),
            ])->filter(fn ($r) => $r->dette > 0)->sortByDesc('dette')->values();

        return view('supplier-payments.index', [
            'payments' => $payments,
            'debts' => $debts,
            'totalDebt' => $debts->sum('dette'),
            'overdue' => SupplierInvoice::overdue()->with('supplier')->get(),
        ]);
    }
}
