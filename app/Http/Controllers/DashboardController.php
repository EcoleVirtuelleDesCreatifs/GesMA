<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Store;
use App\Models\SyncOperation;
use App\Services\StatsService;
use App\Support\Period;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, StatsService $stats)
    {
        $user = $this->user();
        if (! $user->hasPermission('dashboard')) {
            return redirect()->route('deliveries.index');
        }

        $period = Period::fromRequest($request, 'month');
        $city = $user->isDirection() ? $request->input('city') : null;
        $storeIds = $stats->storeIds($user, $this->selectedStoreId($request), $city);

        return view('dashboard', [
            'period' => $period,
            'summary' => $stats->summary($storeIds, $period),
            'timeline' => $stats->timeline($storeIds, $period),
            'topProducts' => $stats->topProducts($storeIds, $period, 8),
            'topCustomers' => $stats->topCustomers($storeIds, $period, 8),
            'lowStock' => $stats->lowStock($storeIds, 10),
            'byStore' => $user->isDirection() ? $stats->byStore($storeIds, $period) : collect(),
            'byCity' => $user->isDirection() ? $stats->byCity($storeIds, $period) : collect(),
            'expensesByCategory' => $stats->expensesByCategory($storeIds, $period),
            'todayOrders' => Order::with(['customer', 'store'])
                ->when($storeIds !== null, fn ($q) => $q->whereIn('store_id', $storeIds))
                ->whereDate('ordered_at', today())->latest('ordered_at')->limit(8)->get(),
            'pendingDeliveries' => Delivery::when($storeIds !== null, fn ($q) => $q->whereIn('store_id', $storeIds))
                ->whereIn('status', DeliveryStatus::active())->count(),
            'syncConflicts' => $user->hasPermission('sync.resolve')
                ? SyncOperation::whereIn('status', ['conflict', 'failed'])->when(! $user->isDirection(), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('store_id', $user->store_id)))->count()
                : 0,
            'stores' => $user->accessibleStores(),
            'cities' => $user->isDirection() ? Store::orderBy('city')->distinct()->pluck('city') : collect(),
            'storeId' => $this->selectedStoreId($request),
            'city' => $city,
        ]);
    }
}
