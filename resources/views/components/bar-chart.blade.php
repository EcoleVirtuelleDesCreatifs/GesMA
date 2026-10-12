@props(['points' => [], 'height' => 220])
{{-- Graphique ventes / dépenses en SVG (aucune bibliothèque externe, fonctionne hors connexion). --}}
@php
    $n = max(count($points), 1);
    $max = max(1, ...array_map(fn ($p) => max($p['sales'], $p['expenses']), $points ?: [['sales' => 0, 'expenses' => 0]]));
    $w = max(560, $n * 26);
    $h = $height;
    $left = 56; $bottom = 26; $top = 10;
    $plotH = $h - $bottom - $top;
    $slot = ($w - $left - 8) / $n;
    $bw = max(3, min(16, $slot / 2 - 2));
    $labelEvery = max(1, (int) ceil($n / 14));
    $fmt = fn ($v) => $v >= 1000000 ? round($v / 1000000, 1).' M' : ($v >= 1000 ? round($v / 1000).' k' : $v);
@endphp
<div class="chart">
    <svg viewBox="0 0 {{ $w }} {{ $h }}" role="img" aria-label="Évolution des ventes et des dépenses">
        @for($i = 0; $i <= 4; $i++)
            @php $y = $top + $plotH - $plotH * $i / 4; @endphp
            <line x1="{{ $left }}" x2="{{ $w }}" y1="{{ $y }}" y2="{{ $y }}" stroke="#EDE6DE" stroke-width="1"/>
            <text x="{{ $left - 6 }}" y="{{ $y + 4 }}" text-anchor="end" font-size="10" fill="#7a6f66">{{ $fmt($max * $i / 4) }}</text>
        @endfor
        @foreach($points as $i => $p)
            @php
                $x = $left + $i * $slot + ($slot - 2 * $bw - 2) / 2;
                $hs = $plotH * $p['sales'] / $max;
                $he = $plotH * $p['expenses'] / $max;
            @endphp
            <rect x="{{ $x }}" y="{{ $top + $plotH - $hs }}" width="{{ $bw }}" height="{{ max(0, $hs) }}" fill="#8B5A2B" rx="2"><title>{{ $p['label'] }} — ventes : {{ money($p['sales']) }}</title></rect>
            <rect x="{{ $x + $bw + 2 }}" y="{{ $top + $plotH - $he }}" width="{{ $bw }}" height="{{ max(0, $he) }}" fill="#D4A373" rx="2"><title>{{ $p['label'] }} — dépenses : {{ money($p['expenses']) }}</title></rect>
            @if($i % $labelEvery === 0)
                <text x="{{ $x + $bw }}" y="{{ $h - 8 }}" text-anchor="middle" font-size="10" fill="#7a6f66">{{ $p['label'] }}</text>
            @endif
        @endforeach
    </svg>
    <div class="legend"><span><i style="background:#8B5A2B"></i>Ventes</span><span><i style="background:#D4A373"></i>Dépenses</span></div>
</div>
