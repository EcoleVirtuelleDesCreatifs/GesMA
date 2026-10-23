<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\Sequence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function dashboard(Request $request)
    {
        abort_unless($request->user()->hasPermission('suppliers.view'), 403);

        $openOrders = \App\Models\PurchaseOrder::open();
        $invoices = \App\Models\SupplierInvoice::query();

        return view('suppliers.dashboard', [
            'activeSuppliers' => Supplier::where('is_active', true)->count(),
            'openOrders' => (clone $openOrders)->count(),
            'monthPurchases' => (float) \App\Models\PurchaseOrder::whereNotIn('status', ['brouillon', 'annulee'])
                ->whereBetween('ordered_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total'),
            'pendingReceipts' => (int) \App\Models\PurchaseOrderItem::whereHas('purchaseOrder', fn ($q) => $q->open())
                ->selectRaw('COALESCE(SUM(quantity - quantity_received),0) as reste')->value('reste'),
            'totalDebt' => (float) \App\Models\SupplierInvoice::unpaid()->get()->sum->balance(),
            'overdueInvoices' => \App\Models\SupplierInvoice::overdue()->count(),
            'monthPayments' => (float) \App\Models\SupplierPayment::whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'returnsCount' => \App\Models\SupplierReturn::whereMonth('returned_at', now()->month)->whereYear('returned_at', now()->year)->count(),
            // Alertes
            'toValidate' => \App\Models\PurchaseOrder::where('status', 'en_attente')->with('supplier')->latest()->limit(10)->get(),
            'lateDeliveries' => \App\Models\PurchaseOrder::open()->whereNotNull('expected_at')->where('expected_at', '<', today())->with('supplier')->latest('expected_at')->limit(10)->get(),
            'partialOrders' => \App\Models\PurchaseOrder::where('status', 'partielle')->with('supplier')->limit(10)->get(),
            'dueSoon' => \App\Models\SupplierInvoice::unpaid()->whereNotNull('due_at')->whereBetween('due_at', [today(), today()->addDays(7)])->with('supplier')->get(),
            'overdue' => \App\Models\SupplierInvoice::overdue()->with('supplier')->limit(10)->get(),
            'topSuppliers' => Supplier::withSum('purchaseOrders as achats_total', 'total')
                ->withCount('products')->orderByDesc('achats_total')->limit(10)->get(),
            // Séries pour graphiques (12 derniers mois)
            'purchasesSeries' => $this->monthlySeries(\App\Models\PurchaseOrder::whereNotIn('status', ['brouillon', 'annulee']), 'ordered_at', 'total'),
            'paymentsSeries' => $this->monthlySeries(\App\Models\SupplierPayment::query(), 'paid_at', 'amount'),
        ]);
    }

    private function monthlySeries($query, string $dateCol, string $sumCol): array
    {
        $from = now()->subMonths(11)->startOfMonth();
        // Groupement en PHP : compatible MySQL et SQLite
        $rows = (clone $query)->where($dateCol, '>=', $from)->get([$dateCol, $sumCol])
            ->groupBy(fn ($r) => \Illuminate\Support\Carbon::parse($r->{$dateCol})->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum($sumCol));

        $series = [];
        for ($i = 11; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $series[$key] = (float) ($rows[$key] ?? 0);
        }

        return $series;
    }

    public function index(Request $request)
    {
        $query = Supplier::query()
            ->search($request->input('q'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->input('status') === 'actif'))
            ->withCount('products')
            ->orderBy('name');

        $suppliers = $query->paginate(30)->withQueryString();

        // Dette calculée par fournisseur (factures - paiements)
        $debts = DB::table('supplier_invoices')
            ->selectRaw('supplier_id, SUM(total) - COALESCE((SELECT SUM(amount) FROM supplier_payments WHERE supplier_payments.supplier_id = supplier_invoices.supplier_id),0) as dette')
            ->groupBy('supplier_id')->pluck('dette', 'supplier_id');

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'debts' => $debts,
            'types' => config('gesma.supplier_types'),
        ]);
    }

    /** Liste globale des produits proposés par les fournisseurs */
    public function productsIndex(Request $request)
    {
        $rows = DB::table('supplier_products')
            ->join('suppliers', 'suppliers.id', '=', 'supplier_products.supplier_id')
            ->join('products', 'products.id', '=', 'supplier_products.product_id')
            ->select('supplier_products.*', 'suppliers.name as supplier_name', 'suppliers.code as supplier_code',
                'products.name as product_name', 'products.reference as product_reference', 'products.price as sale_price')
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_products.supplier_id', $request->input('supplier_id')))
            ->when($request->filled('q'), fn ($q) => $q->where('products.name', 'like', '%'.$request->input('q').'%')
                ->orWhere('products.reference', 'like', '%'.$request->input('q').'%'))
            ->orderBy('suppliers.name')->orderBy('products.name')
            ->paginate(40)->withQueryString();

        return view('suppliers.products', [
            'rows' => $rows,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function create()
    {
        return view('suppliers.form', ['supplier' => new Supplier(['is_active' => true, 'country' => "Côte d'Ivoire"])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $supplier = DB::transaction(function () use ($data) {
            return Supplier::create($data + ['code' => $this->nextCode()]);
        });
        ActivityLog::record('fournisseur.creation', "Fournisseur {$supplier->code} « {$supplier->name} » créé", $supplier);

        return redirect()->route('suppliers.show', $supplier)->with('success', "Fournisseur {$supplier->code} enregistré.");
    }

    public function show(Supplier $supplier)
    {
        $supplier->load(['products' => fn ($q) => $q->orderBy('name'), 'documents.user']);

        return view('suppliers.show', [
            'supplier' => $supplier,
            'orders' => $supplier->purchaseOrders()->with('store')->latest()->limit(20)->get(),
            'receipts' => $supplier->receipts()->with('store')->latest()->limit(20)->get(),
            'invoices' => $supplier->invoices()->with('payments')->latest('invoiced_at')->limit(20)->get(),
            'payments' => $supplier->payments()->with('invoice')->latest('paid_at')->limit(20)->get(),
            'returns' => $supplier->returns()->latest('returned_at')->limit(20)->get(),
            'debt' => $supplier->debt(),
            'allProducts' => Product::orderBy('name')->get(['id', 'reference', 'name', 'price']),
            'activities' => ActivityLog::where('subject_type', $supplier->getMorphClass())->where('subject_id', $supplier->id)->latest()->limit(20)->get(),
        ]);
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.form', ['supplier' => $supplier]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $before = $supplier->only('name', 'type', 'phone', 'email', 'city', 'payment_terms', 'is_active');
        $supplier->update($this->validated($request));
        ActivityLog::record('fournisseur.modification', "Fournisseur {$supplier->code} modifié", $supplier, ['avant' => $before]);

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Fournisseur mis à jour.');
    }

    /** Archivage : un fournisseur avec historique n'est jamais supprimé */
    public function destroy(Supplier $supplier)
    {
        DB::transaction(function () use ($supplier) {
            $supplier->update(['is_active' => false]);
            ActivityLog::record('fournisseur.archivage', "Fournisseur {$supplier->code} « {$supplier->name} » archivé", $supplier);
        });

        return redirect()->route('suppliers.index')->with('success', 'Fournisseur archivé (historique conservé).');
    }

    public function restore(Supplier $supplier)
    {
        $supplier->update(['is_active' => true]);

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Fournisseur réactivé.');
    }

    /** Association produit ↔ fournisseur */
    public function attachProduct(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id', Rule::unique('supplier_products')->where('supplier_id', $supplier->id)],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
        ]);

        $supplier->products()->attach($data['product_id'], [
            'purchase_price' => $data['purchase_price'],
            'min_quantity' => $data['min_quantity'] ?? 1,
            'lead_time_days' => $data['lead_time_days'] ?? null,
        ]);

        return back()->with('success', 'Produit associé au fournisseur.');
    }

    public function updateProduct(Request $request, Supplier $supplier, Product $product)
    {
        $data = $request->validate([
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        // Le prix de vente du produit n'est pas modifié ici (règle métier)
        $supplier->products()->updateExistingPivot($product->id, [
            'purchase_price' => $data['purchase_price'],
            'min_quantity' => $data['min_quantity'] ?? 1,
            'lead_time_days' => $data['lead_time_days'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Produit fournisseur mis à jour.');
    }

    public function detachProduct(Supplier $supplier, Product $product)
    {
        $supplier->products()->detach($product->id);

        return back()->with('success', 'Produit retiré du fournisseur.');
    }

    /** Pièces jointes de la fiche fournisseur */
    public function attachDocument(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'file' => ['required', 'file', 'max:8192', 'mimes:pdf,jpg,jpeg,png'],
        ]);
        $path = $request->file('file')->store('supplier-documents');
        SupplierDocument::create([
            'supplier_id' => $supplier->id,
            'name' => $data['name'],
            'path' => $path,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Document ajouté.');
    }

    public function downloadDocument(SupplierDocument $document)
    {
        return Storage::download($document->path, $document->name);
    }

    private function nextCode(): string
    {
        $num = Sequence::next('FOUR', 4); // FOUR-2026-0001
        $parts = explode('-', $num);

        return 'FOUR-'.end($parts);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'type' => ['nullable', Rule::in(array_keys(config('gesma.supplier_types')))],
            'contact_name' => ['nullable', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'tax_id' => ['nullable', 'string', 'max:60'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
