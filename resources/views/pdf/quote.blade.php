@include('pdf.commercial', [
    'title' => mb_strtoupper(config('gesma.quotes.title', 'Devis')),
    'arrete' => 'Arrêté le présent devis',
    'reference' => $document->order?->number,
    'validity' => $document->valid_until
        ? 'Validité du devis : '.$document->valid_until->format('d/m/Y').' ('.max(0, (int) $document->issued_at->diffInDays($document->valid_until)).' jours)'
        : null,
    'conditions' => config('gesma.quotes.conditions', []),
    'statusNote' => match ($document->status) {
        \App\Enums\QuoteStatus::Refused => 'DEVIS REFUSÉ',
        \App\Enums\QuoteStatus::Expired => 'DEVIS EXPIRÉ',
        default => null,
    },
])
