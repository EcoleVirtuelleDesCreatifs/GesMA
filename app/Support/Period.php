<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Période de consultation : jour, semaine, mois, année ou période personnalisée. */
class Period
{
    public const OPTIONS = [
        'today' => "Aujourd'hui",
        'yesterday' => 'Hier',
        'week' => 'Cette semaine',
        'month' => 'Ce mois',
        'last_month' => 'Mois dernier',
        'year' => 'Cette année',
        'custom' => 'Personnalisée',
    ];

    public function __construct(public Carbon $from, public Carbon $to, public string $key = 'custom') {}

    public static function fromRequest(Request $request, string $default = 'month'): self
    {
        $key = $request->input('period', $default);
        if ($key === 'custom' || ($request->filled('from') && ! $request->filled('period'))) {
            $from = self::safeDate($request->input('from')) ?? now()->startOfMonth();
            $to = self::safeDate($request->input('to')) ?? now();
            if ($to->lt($from)) {
                [$from, $to] = [$to, $from];
            }

            return new self($from->copy()->startOfDay(), $to->copy()->endOfDay(), 'custom');
        }

        return self::make(array_key_exists($key, self::OPTIONS) ? $key : $default);
    }

    public static function make(string $key): self
    {
        $now = now();

        return match ($key) {
            'today' => new self($now->copy()->startOfDay(), $now->copy()->endOfDay(), $key),
            'yesterday' => new self($now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), $key),
            'week' => new self($now->copy()->startOfWeek(), $now->copy()->endOfWeek(), $key),
            'last_month' => new self($now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth(), $key),
            'year' => new self($now->copy()->startOfYear(), $now->copy()->endOfYear(), $key),
            default => new self($now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'month'),
        };
    }

    public function label(): string
    {
        if ($this->from->isSameDay($this->to)) {
            return $this->from->translatedFormat('l j F Y');
        }

        return 'du '.$this->from->format('d/m/Y').' au '.$this->to->format('d/m/Y');
    }

    /** Granularité adaptée pour les graphiques. */
    public function groupFormat(): string
    {
        $days = $this->from->diffInDays($this->to);

        return $days > 92 ? 'month' : 'day';
    }

    public function query(): array
    {
        return ['period' => $this->key, 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
    }

    private static function safeDate(?string $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
