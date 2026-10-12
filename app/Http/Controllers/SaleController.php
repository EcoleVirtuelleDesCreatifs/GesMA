<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Store;
use App\Services\StatsService;
use App\Support\Period;
use Illuminate\Http\Request;

/** Consultation des ventes par période, ville et magasin. */
class SaleController extends Controller
{
    public function index(Request $request, StatsService $stats)
    {
        $user = $this->user();
        $period = Period::fromRequest($request, 'today');
        $city = $user->isDirection() ? $request->input('city') : null;
        $storeIds = $stats->storeIds($user, $this->selectedStoreId($request), $city);

        $orders = $stats->orders($storeIds, $period)->with(['customer', 'store'])->latest('ordered_at');

        return view('sales.index', [
            'period' => $period,
            'summary' => $stats->summary($storeIds, $period),
            'timeline' => $stats->timeline($storeIds, $period),
            'byStore' => $stats->byStore($storeIds, $period),
            'byCity' => $stats->byCity($storeIds, $period),
            'topProducts' => $stats->topProducts($storeIds, $period, 10),
            'validOrders' => (clone $orders)->valid()->paginate(20, ['*'], 'page')->withQueryString(),
            'cancelledOrReturned' => (clone $orders)->whereIn('status', [OrderStatus::Cancelled->value, OrderStatus::Returned->value])->limit(50)->get(),
            'stores' => $user->accessibleStores(),
            'cities' => $user->isDirection() ? Store::orderBy('city')->distinct()->pluck('city') : collect(),
            'storeId' => $this->selectedStoreId($request),
            'city' => $city,
        ]);
    }
}
