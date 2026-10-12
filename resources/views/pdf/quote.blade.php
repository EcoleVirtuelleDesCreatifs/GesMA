@include('pdf.document', [
    'kind' => 'Devis',
    'validity' => $document->valid_until ? 'Valable jusqu\'au '.$document->valid_until->format('d/m/Y') : null,
    'footer' => config('gesma.quotes.footer'),
])
