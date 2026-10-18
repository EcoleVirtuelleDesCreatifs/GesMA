<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Période de consultation : jour, semaine, mois, année ou personnalisée */
class Period
{
    public const OPTIONS = [
        'today' => 'Aujourd’hui',
        'yesterday' => 'Hier',
        'week' => 'Cette semaine',
        'month' => 'Ce mois',
        'last_month' => 'Mois dernier',
        'year' => 'Cette année',
        'custom' => 'Période personnalisée',
    ];

    public function __construct(
        public Carbon $from,
        public Carbon $to,
        public string $key,
    ) {
    }

    public static function fromRequest(Request $request, string $default = 'month'): self
    {
        $key = $request->input('period', $default);
        if (! array_key_exists($key, self::OPTIONS)) {
            $key = $default;
        }

        $now = now();
        [$from, $to] = match ($key) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom' => self::custom($request),
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        return new self($from, $to, $key);
    }

    private static function custom(Request $request): array
    {
        try {
            $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
            $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        } catch (\Throwable) {
            return [now()->startOfMonth(), now()->endOfDay()];
        }
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    public function label(): string
    {
        if ($this->from->isSameDay($this->to)) {
            return $this->from->format('d/m/Y');
        }

        return 'du '.$this->from->format('d/m/Y').' au '.$this->to->format('d/m/Y');
    }

    public function query(): array
    {
        return [
            'period' => $this->key,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
        ];
    }
}
