<?php

namespace App\Http\Controllers;

use App\Services\StatsService;
use App\Support\Period;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, StatsService $stats)
    {
        $user = $request->user();
        if (! $user->hasPermission('dashboard.view')) {
            return redirect()->route($user->role === 'logistique' ? 'deliveries.index' : 'orders.index');
        }

        $period = Period::fromRequest($request, 'month');
        $storeId = $this->storeFilter($request);

        return view('dashboard', [
            'period' => $period,
            'storeId' => $storeId,
            'stores' => $this->selectableStores($request),
            'stats' => $stats->dashboard($user, $storeId, $period),
        ]);
    }
}
