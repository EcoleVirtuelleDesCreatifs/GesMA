<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Stock;
use App\Models\Store;
use App\Models\User;
use App\Support\Period;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Indicateurs du tableau de bord et des rapports */
class StatsService
{
    public function dashboard(User $user, ?int $storeId, Period $period): array
    {
        $orders = fn () => Order::visibleTo($user, $storeId)->between($period->from, $period->to);

        $byStatus = $orders()->select('status', DB::raw('COUNT(*) as n'), DB::raw('SUM(total) as amount'))
            ->groupBy('status')->get()->keyBy('status');

        $revenue = (float) $orders()->countedAsSale()->sum('total');
        $expenses = (float) Expense::visibleTo($user, $storeId)
            ->whereBetween('spent_at', [$period->from->toDateString(), $period->to->toDateString()])->sum('amount');

        return [
            'revenue' => $revenue,
            'revenue_delivered' => (float) ($byStatus['livree']->amount ?? 0),
            'collected' => (float) $orders()->countedAsSale()->sum('amount_paid'),
            'orders_count' => (int) $byStatus->sum('n'),
            'sales_count' => (int) $orders()->countedAsSale()->count(),
            'by_status' => collect(config('gesma.order_statuses'))->map(fn ($label, $key) => [
                'label' => $label,
                'count' => (int) ($byStatus[$key]->n ?? 0),
                'amount' => (float) ($byStatus[$key]->amount ?? 0),
            ]),
            'today_orders' => Order::visibleTo($user, $storeId)
                ->whereBetween('ordered_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            'expenses' => $expenses,
            'result' => $revenue - $expenses,
            'average_basket' => $byStatus->sum('n') ? $revenue / max(1, $orders()->countedAsSale()->count()) : 0,
            'top_products' => $this->topProducts($user, $storeId, $period),
            'low_stocks' => $this->lowStocks($user, $storeId),
            'top_customers' => $this->topCustomers($user, $storeId, $period),
            'by_store' => $this->byStore($user, $storeId, $period),
            'by_city' => $this->byCity($user, $storeId, $period),
            'daily' => $this->daily($user, $storeId, $period),
            'expenses_by_category' => $this->expensesByCategory($user, $storeId, $period),
        ];
    }

    public function topProducts(User $user, ?int $storeId, Period $period, int $limit = 10): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.id', Order::visibleTo($user, $storeId)->between($period->from, $period->to)->countedAsSale()->select('orders.id'))
            ->select('order_items.product_id', 'order_items.product_name', 'order_items.product_reference',
                DB::raw('SUM(order_items.quantity) as quantity'), DB::raw('SUM(order_items.total) as amount'))
            ->groupBy('order_items.product_id', 'order_items.product_name', 'order_items.product_reference')
            ->orderByDesc('quantity')
            ->limit($limit)
            ->get();
    }

    public function lowStocks(User $user, ?int $storeId, int $limit = 20): Collection
    {
        return Stock::visibleTo($user, $storeId)->low()
            ->whereHas('product', fn ($q) => $q->where('is_available', true))
            ->with('product:id,name,reference', 'store:id,name')
            ->orderBy('quantity')
            ->limit($limit)
            ->get();
    }

    public function lowStockCount(User $user): int
    {
        return Stock::visibleTo($user)->low()->whereHas('product', fn ($q) => $q->where('is_available', true))->count();
    }

    /** Clients : les plus fréquents et ceux qui achètent le plus */
    public function topCustomers(User $user, ?int $storeId, ?Period $period = null, int $limit = 10, string $orderBy = 'amount'): Collection
    {
        $query = Order::visibleTo($user, $storeId)->countedAsSale()->whereNotNull('customer_id');
        if ($period) {
            $query->between($period->from, $period->to);
        }

        $rows = $query->select('customer_id', DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(total) as amount'), DB::raw('MAX(ordered_at) as last_order'))
            ->groupBy('customer_id')
            ->orderByDesc($orderBy === 'count' ? 'orders_count' : 'amount')
            ->limit($limit)
            ->get();

        $customers = Customer::whereIn('id', $rows->pluck('customer_id'))->get()->keyBy('id');

        return $rows->map(function ($row) use ($customers) {
            $row->customer = $customers[$row->customer_id] ?? null;

            return $row;
        })->filter(fn ($r) => $r->customer)->values();
    }

    public function byStore(User $user, ?int $storeId, Period $period): Collection
    {
        $stores = Store::whereIn('id', $storeId ? [$storeId] : $user->accessibleStoreIds())->orderBy('name')->get();
        $sales = Order::visibleTo($user, $storeId)->between($period->from, $period->to)
            ->select('store_id',
                DB::raw("SUM(CASE WHEN status NOT IN ('annulee','retournee') THEN total ELSE 0 END) as revenue"),
                DB::raw("SUM(CASE WHEN status NOT IN ('annulee','retournee') THEN 1 ELSE 0 END) as sales_count"),
                DB::raw("SUM(CASE WHEN status = 'annulee' THEN 1 ELSE 0 END) as cancelled"),
                DB::raw("SUM(CASE WHEN status = 'retournee' THEN 1 ELSE 0 END) as returned"))
            ->groupBy('store_id')->get()->keyBy('store_id');
        $expenses = Expense::visibleTo($user, $storeId)
            ->whereBetween('spent_at', [$period->from->toDateString(), $period->to->toDateString()])
            ->select('store_id', DB::raw('SUM(amount) as total'))->groupBy('store_id')->pluck('total', 'store_id');

        return $stores->map(fn (Store $store) => (object) [
            'store' => $store,
            'city' => $store->city,
            'revenue' => (float) ($sales[$store->id]->revenue ?? 0),
            'sales_count' => (int) ($sales[$store->id]->sales_count ?? 0),
            'cancelled' => (int) ($sales[$store->id]->cancelled ?? 0),
            'returned' => (int) ($sales[$store->id]->returned ?? 0),
            'expenses' => (float) ($expenses[$store->id] ?? 0),
            'result' => (float) ($sales[$store->id]->revenue ?? 0) - (float) ($expenses[$store->id] ?? 0),
        ]);
    }

    public function byCity(User $user, ?int $storeId, Period $period): Collection
    {
        return $this->byStore($user, $storeId, $period)->groupBy('city')->map(fn ($rows, $city) => (object) [
            'city' => $city,
            'stores' => $rows->count(),
            'revenue' => $rows->sum('revenue'),
            'sales_count' => $rows->sum('sales_count'),
            'expenses' => $rows->sum('expenses'),
            'result' => $rows->sum('result'),
        ])->sortByDesc('revenue')->values();
    }

    public function daily(User $user, ?int $storeId, Period $period): Collection
    {
        $rows = Order::visibleTo($user, $storeId)->between($period->from, $period->to)->countedAsSale()
            ->select(DB::raw('DATE(ordered_at) as day'), DB::raw('SUM(total) as amount'), DB::raw('COUNT(*) as n'))
            ->groupBy(DB::raw('DATE(ordered_at)'))->orderBy('day')->get()->keyBy('day');

        // Limite l'histogramme à 62 jours pour rester lisible
        $days = collect();
        $cursor = $period->from->copy()->startOfDay();
        $end = $period->to->copy()->min(now()->endOfDay());
        if ($cursor->diffInDays($end) > 62) {
            $cursor = $end->copy()->subDays(61)->startOfDay();
        }
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $days->push((object) ['day' => $cursor->copy(), 'amount' => (float) ($rows[$key]->amount ?? 0), 'count' => (int) ($rows[$key]->n ?? 0)]);
            $cursor->addDay();
        }

        return $days;
    }

    public function expensesByCategory(User $user, ?int $storeId, Period $period): Collection
    {
        return Expense::visibleTo($user, $storeId)
            ->whereBetween('spent_at', [$period->from->toDateString(), $period->to->toDateString()])
            ->select('category', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as n'))
            ->groupBy('category')->orderByDesc('total')->get()
            ->map(function ($row) {
                $row->label = config("gesma.expense_categories.{$row->category}", $row->category);

                return $row;
            });
    }
}
