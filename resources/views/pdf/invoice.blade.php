@include('pdf.document', [
    'kind' => 'Facture',
    'validity' => $document->due_at ? 'Échéance : '.$document->due_at->format('d/m/Y') : null,
    'reference' => $document->order ? 'Commande : '.$document->order->number : null,
    'paid' => $document->amount_paid,
    'statusNote' => $document->status === \App\Enums\InvoiceStatus::Cancelled ? 'FACTURE ANNULÉE' : ($document->status === \App\Enums\InvoiceStatus::Paid ? 'FACTURE ACQUITTÉE' : null),
    'footer' => config('gesma.invoices.footer'),
])
