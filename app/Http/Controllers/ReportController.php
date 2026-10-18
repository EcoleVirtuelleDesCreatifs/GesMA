<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Order;
use App\Models\Stock;
use App\Models\Store;
use App\Services\StatsService;
use App\Support\Period;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Rapports PDF : ventes, stocks, commandes, dépenses */
class ReportController extends Controller
{
    public const TYPES = [
        'ventes' => 'Rapport des ventes',
        'commandes' => 'Rapport des commandes',
        'stocks' => 'État des stocks',
        'depenses' => 'Rapport des dépenses',
    ];

    public function index(Request $request)
    {
        return view('reports.index', [
            'types' => self::TYPES,
            'stores' => $this->selectableStores($request),
        ]);
    }

    public function pdf(Request $request, StatsService $stats)
    {
        $request->validate(['type' => ['required', Rule::in(array_keys(self::TYPES))]]);
        $type = $request->input('type');
        $user = $request->user();
        $storeId = $this->storeFilter($request);
        $period = Period::fromRequest($request, 'month');

        $data = [
            'title' => self::TYPES[$type],
            'type' => $type,
            'period' => $period,
            'store' => $storeId ? Store::find($storeId) : null,
            'generatedBy' => $user,
        ];

        $data += match ($type) {
            'ventes' => [
                'stats' => $stats->dashboard($user, $storeId, $period),
            ],
            'commandes' => [
                'orders' => Order::visibleTo($user, $storeId)->between($period->from, $period->to)
                    ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                    ->with('customer', 'store')->orderBy('ordered_at')->get(),
            ],
            'stocks' => [
                'stocks' => Stock::visibleTo($user, $storeId)
                    ->join('products', 'products.id', '=', 'stocks.product_id')
                    ->select('stocks.*')->with('product.category', 'store')
                    ->orderBy('products.name')->get(),
            ],
            'depenses' => [
                'expenses' => Expense::visibleTo($user, $storeId)
                    ->whereBetween('spent_at', [$period->from->toDateString(), $period->to->toDateString()])
                    ->with('store')->orderBy('spent_at')->get(),
                'byCategory' => $stats->expensesByCategory($user, $storeId, $period),
                'revenue' => (float) Order::visibleTo($user, $storeId)->between($period->from, $period->to)->countedAsSale()->sum('total'),
            ],
        };

        $filename = sprintf('GesMA-%s-%s.pdf', $type, now()->format('Ymd-His'));
        $orientation = in_array($type, ['commandes', 'stocks'], true) ? 'landscape' : 'portrait';

        return Pdf::loadView('pdf.report', $data)->setPaper('a4', $orientation)->download($filename);
    }
}
