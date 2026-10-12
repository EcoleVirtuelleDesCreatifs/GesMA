<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Concerns\HandlesDocuments;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\NumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    use HandlesDocuments;

    public function index(Request $request)
    {
        $query = Invoice::with(['customer', 'store', 'order'])->visibleTo($this->user())
            ->forStore($this->selectedStoreId($request))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('number', 'like', "%$t%")->orWhereHas('customer', fn ($c) => $c->search($t))));

        return view('invoices.index', [
            'invoices' => (clone $query)->latest('id')->paginate(25)->withQueryString(),
            'totals' => [
                'total' => (clone $query)->where('status', '!=', InvoiceStatus::Cancelled->value)->sum('total'),
                'paid' => (clone $query)->where('status', '!=', InvoiceStatus::Cancelled->value)->sum('amount_paid'),
            ],
            'stores' => $this->user()->accessibleStores(),
        ]);
    }

    public function create(Request $request)
    {
        return view('invoices.form', $this->formData() + [
            'invoice' => new Invoice([
                'issued_at' => today(),
                'due_at' => today()->addDays(config('gesma.invoices.due_days')),
                'customer_id' => $request->integer('customer_id') ?: null,
            ]),
            'customer' => $this->customerFromRequest($request),
        ]);
    }

    public function store(Request $request, NumberGenerator $numbers)
    {
        $data = $this->validateDocument($request, [
            'due_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'amount_paid' => ['nullable', 'integer', 'min:0'],
        ]);

        $invoice = DB::transaction(function () use ($data, $numbers) {
            $invoice = Invoice::create(collect($data)->except('lines')->all() + [
                'number' => $numbers->next(NumberGenerator::INVOICE),
                'user_id' => $this->user()->id,
            ]);
            $invoice->syncLines($data['lines']);
            $invoice->amount_paid = min($invoice->total, (int) ($data['amount_paid'] ?? 0));
            $invoice->refreshPaymentStatus();
            $invoice->save();

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)->with('success', 'Facture '.$invoice->number.' créée.');
    }

    /** Facture générée à partir d'une commande. */
    public function fromOrder(Order $order, NumberGenerator $numbers)
    {
        $this->authorizeStore($order);
        abort_unless($order->customer_id, 422, 'La commande doit être rattachée à un client pour être facturée.');
        if ($existing = $order->invoices()->where('status', '!=', InvoiceStatus::Cancelled->value)->first()) {
            return redirect()->route('invoices.show', $existing)->with('info', 'Cette commande est déjà facturée.');
        }

        $invoice = DB::transaction(function () use ($order, $numbers) {
            $invoice = Invoice::create([
                'number' => $numbers->next(NumberGenerator::INVOICE),
                'customer_id' => $order->customer_id,
                'store_id' => $order->store_id,
                'user_id' => $this->user()->id,
                'order_id' => $order->id,
                'issued_at' => today(),
                'due_at' => today()->addDays(config('gesma.invoices.due_days')),
                'discount' => $order->discount,
                'delivery_fee' => $order->delivery_fee,
            ]);
            $invoice->syncLines($order->items->map(fn ($i) => [
                'product_id' => $i->product_id, 'designation' => $i->product_name, 'reference' => $i->product_reference,
                'unit_price' => $i->unit_price, 'quantity' => $i->quantity,
            ])->all());
            $invoice->amount_paid = min($invoice->total, $order->amount_paid);
            $invoice->refreshPaymentStatus();
            $invoice->save();

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)->with('success', 'Facture '.$invoice->number.' créée.');
    }

    public function show(Invoice $invoice)
    {
        $this->authorizeStore($invoice);

        return view('invoices.show', ['invoice' => $invoice->load(['lines', 'customer', 'store', 'user', 'order', 'quote'])]);
    }

    public function payment(Request $request, Invoice $invoice)
    {
        $this->authorizeStore($invoice);
        abort_if($invoice->status === InvoiceStatus::Cancelled, 422);
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1']]);

        DB::transaction(function () use ($invoice, $data) {
            $invoice->amount_paid = min($invoice->total, $invoice->amount_paid + $data['amount']);
            $invoice->refreshPaymentStatus();
            $invoice->save();
            if ($invoice->order) {
                $invoice->order->update(['amount_paid' => max($invoice->order->amount_paid, min($invoice->order->total, $invoice->amount_paid))]);
            }
        });

        return back()->with('success', 'Paiement de '.money($data['amount']).' enregistré.');
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        $this->authorizeStore($invoice);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $invoice->update(['status' => InvoiceStatus::Cancelled, 'notes' => trim($invoice->notes."\nAnnulée : ".$data['reason'])]);
        AuditLogger::log('invoice.cancelled', 'Facture '.$invoice->number.' annulée : '.$data['reason'], $invoice);

        return back()->with('success', 'Facture annulée.');
    }

    public function pdf(Invoice $invoice)
    {
        $this->authorizeStore($invoice);

        return $this->renderPdf('pdf.invoice', $invoice, $invoice->number.'.pdf');
    }
}
