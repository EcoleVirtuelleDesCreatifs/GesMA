<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Product;
use App\Services\DocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Devis et factures */
class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $storeId = $this->storeFilter($request);

        $documents = Document::visibleTo($user, $storeId)
            ->with('customer', 'store')
            ->when($request->filled('type'), fn($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('q'), fn($q) => $q->where(fn($w) => $w->where('number', 'like', '%' . $request->input('q') . '%')
                ->orWhereHas('customer', fn($c) => $c->search($request->input('q')))))
            ->latest('issued_at')->latest('id')
            ->paginate(30)->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'stores' => $this->selectableStores($request),
            'storeId' => $storeId,
        ]);
    }

    public function create(Request $request)
    {
        $type = $request->input('type', 'devis');
        abort_unless(array_key_exists($type, Document::TYPES), 404);

        return view('documents.create', [
            'type' => $type,
            'stores' => $this->selectableStores($request),
            'products' => Product::where('is_available', true)->orderBy('name')->get(['id', 'name', 'reference', 'price']),
            'customer' => $request->filled('customer_id') ? Customer::find($request->integer('customer_id')) : null,
        ]);
    }

    public function store(Request $request, DocumentService $service)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Document::TYPES))],
            'customer_id' => ['required', 'exists:customers,id'],
            'issued_at' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ], ['customer_id.required' => 'Choisissez un client.']);
        $data['store_id'] = $this->storeForCreation($request);

        // Seule la Direction peut appliquer un prix différent du tarif du produit
        if (! $request->user()->hasPermission('products.price')) {
            foreach ($data['items'] as &$item) {
                if (! empty($item['product_id'])) {
                    unset($item['unit_price']);
                }
            }
            unset($item);
        }

        $document = $service->create($data['type'], $data, $request->user());

        return redirect()->route('documents.show', $document)->with('success', $document->typeLabel() . " {$document->number} créé(e).");
    }

    public function show(Request $request, Document $document)
    {
        $this->authorizeStore($request, $document);
        $document->load('items', 'customer', 'store', 'order', 'sourceDocument', 'user');
        $invoice = $document->isQuote() ? Document::where('source_document_id', $document->id)->first() : null;

        return view('documents.show', compact('document', 'invoice'));
    }

    public function pdf(Request $request, Document $document)
    {
        $this->authorizeStore($request, $document);
        $document->load('items', 'customer', 'store', 'user');

        return Pdf::loadView('pdf.document', ['document' => $document])
            ->setPaper('a4')
            ->download($document->number . '.pdf');
    }

    public function convert(Request $request, Document $document, DocumentService $service)
    {
        $this->authorizeStore($request, $document);
        $invoice = $service->convertQuote($document->load('items'), $request->user());

        return redirect()->route('documents.show', $invoice)->with('success', "Facture {$invoice->number} créée à partir du devis.");
    }

    public function payment(Request $request, Document $document)
    {
        $this->authorizeStore($request, $document);
        abort_if($document->isQuote(), 422);
        $data = $request->validate(['amount_paid' => ['required', 'numeric', 'min:0']]);
        $document->update(['amount_paid' => min((float) $document->total, (float) $data['amount_paid'])]);

        return back()->with('success', 'Montant réglé mis à jour.');
    }

    public function destroy(Request $request, Document $document)
    {
        $this->authorizeStore($request, $document);
        ActivityLog::record(
            'document.suppression',
            "Suppression de {$document->typeLabel()} {$document->number} (" . money($document->total) . ')',
            null,
            $document->only('id', 'type', 'number', 'customer_id', 'order_id', 'total', 'amount_paid'),
            $document->store_id
        );
        $document->delete();

        return redirect()->route('documents.index')->with('success', $document->typeLabel() . " {$document->number} supprimé(e).");
    }
}
