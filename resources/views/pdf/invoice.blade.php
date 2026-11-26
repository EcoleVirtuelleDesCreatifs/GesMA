@include('pdf.commercial', [
    'title' => mb_strtoupper(config('gesma.invoices.title', 'Facture')),
    'arrete' => 'Arrêtée la présente facture',
    'reference' => collect([$document->order?->number, $document->quote?->number])->filter()->implode(' / '),
    'validity' => $document->due_at ? 'Échéance : '.$document->due_at->format('d/m/Y') : null,
    'conditions' => config('gesma.invoices.conditions', []),
    'paid' => $document->amount_paid,
    'statusNote' => $document->status === \App\Enums\InvoiceStatus::Cancelled ? 'FACTURE ANNULÉE' : ($document->status === \App\Enums\InvoiceStatus::Paid ? 'FACTURE ACQUITTÉE' : null),
])
