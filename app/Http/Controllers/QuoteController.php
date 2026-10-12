<?php

namespace App\Http\Controllers;

use App\Enums\OrderSource;
use App\Enums\OrderType;
use App\Enums\QuoteStatus;
use App\Http\Controllers\Concerns\HandlesDocuments;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\NumberGenerator;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuoteController extends Controller
{
    use HandlesDocuments;

    public function index(Request $request)
    {
        return view('quotes.index', [
            'quotes' => Quote::with(['customer', 'store'])->visibleTo($this->user())
                ->forStore($this->selectedStoreId($request))
                ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
                ->when($request->input('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('number', 'like', "%$t%")->orWhereHas('customer', fn ($c) => $c->search($t))))
                ->latest('id')->paginate(25)->withQueryString(),
            'stores' => $this->user()->accessibleStores(),
        ]);
    }

    public function create(Request $request)
    {
        return view('quotes.form', $this->formData() + [
            'quote' => new Quote([
                'issued_at' => today(),
                'valid_until' => today()->addDays(config('gesma.quotes.validity_days')),
                'customer_id' => $request->integer('customer_id') ?: null,
            ]),
            'customer' => $this->customerFromRequest($request),
        ]);
    }

    public function store(Request $request, NumberGenerator $numbers)
    {
        $data = $this->validateDocument($request, ['valid_until' => ['nullable', 'date', 'after_or_equal:issued_at']]);

        $quote = DB::transaction(function () use ($data, $numbers) {
            $quote = Quote::create(collect($data)->except('lines')->all() + [
                'number' => $numbers->next(NumberGenerator::QUOTE),
                'user_id' => $this->user()->id,
                'status' => QuoteStatus::Draft,
            ]);
            $quote->syncLines($data['lines']);

            return $quote;
        });

        return redirect()->route('quotes.show', $quote)->with('success', 'Devis '.$quote->number.' créé.');
    }

    public function show(Quote $quote)
    {
        $this->authorizeStore($quote);

        return view('quotes.show', ['quote' => $quote->load(['lines', 'customer', 'store', 'user', 'order', 'invoices'])]);
    }

    public function edit(Quote $quote)
    {
        $this->authorizeStore($quote);
        abort_if($quote->order_id || $quote->status === QuoteStatus::Accepted, 422, 'Un devis accepté ne peut plus être modifié.');

        return view('quotes.form', $this->formData() + ['quote' => $quote->load('lines'), 'customer' => $quote->customer]);
    }

    public function update(Request $request, Quote $quote)
    {
        $this->authorizeStore($quote);
        abort_if($quote->order_id || $quote->status === QuoteStatus::Accepted, 422, 'Un devis accepté ne peut plus être modifié.');
        $data = $this->validateDocument($request, ['valid_until' => ['nullable', 'date', 'after_or_equal:issued_at']]);

        DB::transaction(function () use ($quote, $data) {
            $quote->update(collect($data)->except('lines')->all());
            $quote->syncLines($data['lines']);
        });

        return redirect()->route('quotes.show', $quote)->with('success', 'Devis mis à jour.');
    }

    public function updateStatus(Request $request, Quote $quote)
    {
        $this->authorizeStore($quote);
        $data = $request->validate(['status' => ['required', Rule::enum(QuoteStatus::class)]]);
        $quote->update(['status' => $data['status']]);

        return back()->with('success', 'Statut du devis : '.$quote->status->label().'.');
    }

    public function pdf(Quote $quote)
    {
        $this->authorizeStore($quote);

        return $this->renderPdf('pdf.quote', $quote, $quote->number.'.pdf');
    }

    /** Transforme le devis accepté en commande (le stock est vérifié et réservé). */
    public function convertToOrder(Request $request, Quote $quote, OrderService $orders)
    {
        $this->authorizeStore($quote);
        abort_if($quote->order_id, 422, 'Ce devis a déjà été transformé en commande.');
        $lines = $quote->lines->filter(fn ($l) => $l->product_id);
        abort_if($lines->isEmpty(), 422, 'Le devis ne contient aucun produit du catalogue.');

        $order = DB::transaction(function () use ($quote, $lines, $orders, $request) {
            $order = $orders->create([
                'type' => OrderType::Order->value,
                'source' => OrderSource::Gesma->value,
                'store_id' => $quote->store_id,
                'customer_id' => $quote->customer_id,
                'items' => $lines->map(fn ($l) => ['product_id' => $l->product_id, 'quantity' => $l->quantity, 'unit_price' => $l->unit_price])->values()->all(),
                'discount' => $quote->discount,
                'requires_delivery' => $quote->delivery_fee > 0 || $request->boolean('requires_delivery'),
                'delivery' => ['fee' => $quote->delivery_fee, 'address' => $quote->customer->address, 'city' => $quote->customer->city],
                'notes' => 'Issue du devis '.$quote->number,
            ], $this->user());
            $quote->update(['order_id' => $order->id, 'status' => QuoteStatus::Accepted]);

            return $order;
        });

        return redirect()->route('orders.show', $order)->with('success', 'Commande '.$order->number.' créée à partir du devis.');
    }

    public function convertToInvoice(Quote $quote, NumberGenerator $numbers)
    {
        $this->authorizeStore($quote);
        $invoice = DB::transaction(function () use ($quote, $numbers) {
            $invoice = Invoice::create([
                'number' => $numbers->next(NumberGenerator::INVOICE),
                'customer_id' => $quote->customer_id,
                'store_id' => $quote->store_id,
                'user_id' => $this->user()->id,
                'order_id' => $quote->order_id,
                'quote_id' => $quote->id,
                'issued_at' => today(),
                'due_at' => today()->addDays(config('gesma.invoices.due_days')),
                'discount' => $quote->discount,
                'delivery_fee' => $quote->delivery_fee,
                'notes' => $quote->notes,
            ]);
            $invoice->syncLines($quote->lines->map(fn ($l) => $l->only(['product_id', 'designation', 'reference', 'unit_price', 'quantity']))->all());
            $quote->update(['status' => QuoteStatus::Accepted]);

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)->with('success', 'Facture '.$invoice->number.' créée.');
    }
}
