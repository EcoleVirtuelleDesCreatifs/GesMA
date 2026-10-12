<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Product;
use App\Models\Store;
use App\Services\StatsService;
use App\Support\Period;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const TYPES = ['ventes', 'stocks', 'commandes', 'depenses'];

    public const LABELS = [
        'ventes' => 'Rapport des ventes',
        'stocks' => 'État des stocks',
        'commandes' => 'Rapport des commandes',
        'depenses' => 'Rapport des dépenses',
    ];

    public function index(Request $request)
    {
        $user = $this->user();

        return view('reports.index', [
            'period' => Period::fromRequest($request, 'month'),
            'stores' => $user->accessibleStores(),
            'cities' => $user->isDirection() ? Store::orderBy('city')->distinct()->pluck('city') : collect(),
            'storeId' => $this->selectedStoreId($request),
        ]);
    }

    public function pdf(Request $request, string $type, StatsService $stats)
    {
        $data = $this->build($request, $type, $stats);
        $pdf = Pdf::loadView('pdf.report-'.$type, $data)->setOption(['isPhpEnabled' => true, 'defaultFont' => 'DejaVu Sans'])->setPaper('a4', $type === 'commandes' ? 'landscape' : 'portrait');
        $filename = sprintf('rapport-%s-%s.pdf', $type, $data['period']->from->format('Ymd'));

        return $request->boolean('inline') ? $pdf->stream($filename) : $pdf->download($filename);
    }

    /** Export tableur (CSV compatible Excel). */
    public function csv(Request $request, string $type, StatsService $stats): StreamedResponse
    {
        $data = $this->build($request, $type, $stats);
        [$header, $rows] = match ($type) {
            'ventes' => [
                ['Magasin', 'Ville', 'Commandes', "Chiffre d'affaires", 'Annulées/retournées', 'Dépenses', 'Résultat'],
                $data['byStore']->map(fn ($r) => [$r->store->name, $r->store->city, $r->orders, $r->revenue, $r->cancelled_returned, $r->expenses, $r->result]),
            ],
            'stocks' => [
                array_merge(['Référence', 'Produit', 'Catégorie', 'Prix'], $data['stores']->pluck('name')->all(), ['Total']),
                $data['products']->map(fn ($p) => array_merge(
                    [$p->reference, $p->name, $p->category?->name, $p->price],
                    $data['stores']->map(fn ($s) => $p->quantityIn($s->id))->all(),
                    [$data['stores']->sum(fn ($s) => $p->quantityIn($s->id))]
                )),
            ],
            'commandes' => [
                ['Numéro', 'Date', 'Type', 'Origine', 'Client', 'Magasin', 'Statut', 'Total', 'Payé'],
                $data['orders']->map(fn ($o) => [$o->number, $o->ordered_at->format('d/m/Y H:i'), $o->type->label(), $o->source->label(), $o->customer?->name, $o->store->name, $o->status->label(), $o->total, $o->amount_paid]),
            ],
            'depenses' => [
                ['Date', 'Magasin', 'Catégorie', 'Description', 'Montant', 'Saisie par'],
                $data['expenses']->map(fn ($e) => [$e->spent_at->format('d/m/Y'), $e->store->name, $e->category->name, $e->description, $e->amount, $e->user?->name]),
            ],
        };

        $filename = sprintf('rapport-%s-%s.csv', $type, $data['period']->from->format('Ymd'));

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function build(Request $request, string $type, StatsService $stats): array
    {
        $user = $this->user();
        $period = Period::fromRequest($request, 'month');
        $city = $user->isDirection() ? $request->input('city') : null;
        $storeId = $this->selectedStoreId($request);
        $storeIds = $stats->storeIds($user, $storeId, $city);
        $scopeLabel = $storeId ? Store::find($storeId)?->name : ($city ? 'Ville de '.$city : 'Tous les magasins');

        $base = [
            'title' => self::LABELS[$type],
            'period' => $period,
            'scopeLabel' => $scopeLabel,
            'generatedBy' => $user->name,
        ];

        return $base + match ($type) {
            'ventes' => [
                'summary' => $stats->summary($storeIds, $period),
                'byStore' => $stats->byStore($storeIds, $period),
                'byCity' => $stats->byCity($storeIds, $period),
                'topProducts' => $stats->topProducts($storeIds, $period, 15),
                'topCustomers' => $stats->topCustomers($storeIds, $period, 10),
                'timeline' => $stats->timeline($storeIds, $period),
                'cancelledOrReturned' => $stats->orders($storeIds, $period)->with(['customer', 'store'])
                    ->whereIn('status', [OrderStatus::Cancelled->value, OrderStatus::Returned->value])->latest('ordered_at')->get(),
            ],
            'stocks' => [
                'stores' => Store::when($storeIds !== null, fn ($q) => $q->whereIn('id', $storeIds))->orderBy('name')->get(),
                'products' => Product::with(['category', 'stocks'])->orderBy('name')->get(),
                'lowStock' => $stats->lowStock($storeIds, 500),
            ],
            'commandes' => [
                'summary' => $stats->summary($storeIds, $period),
                'orders' => $stats->orders($storeIds, $period)->with(['customer', 'store'])->oldest('ordered_at')->get(),
            ],
            'depenses' => [
                'expenses' => $stats->expenses($storeIds, $period)->with(['category', 'store', 'user'])->oldest('spent_at')->get(),
                'byCategory' => $stats->expensesByCategory($storeIds, $period),
                'byStore' => $stats->byStore($storeIds, $period),
                'summary' => $stats->summary($storeIds, $period),
            ],
        };
    }
}
