<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Stock;
use App\Models\Store;
use App\Models\User;
use App\Support\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs du tableau de bord et des rapports.
 * Le chiffre d'affaires ne prend en compte que les commandes valides :
 * les annulations et les retours sont comptés à part.
 */
class StatsService
{
    /** Identifiants des magasins à inclure (null = tous). */
    public function storeIds(User $user, $storeId = null, $city = null): ?array
    {
        if ($storeId) {
            return $user->canAccessStore($storeId) ? [(int) $storeId] : [0];
        }
        if (! $user->isDirection()) {
            return [(int) ($user->store_id ?? 0)];
        }
        if ($city) {
            return Store::where('city', $city)->pluck('id')->all() ?: [0];
        }

        return null;
    }

    public function orders(?array $storeIds, Period $period): Builder
    {
        return Order::query()
            ->when($storeIds !== null, fn ($q) => $q->whereIn('orders.store_id', $storeIds))
            ->whereBetween('orders.ordered_at', [$period->from, $period->to]);
    }

    public function expenses(?array $storeIds, Period $period): Builder
    {
        return Expense::query()
            ->when($storeIds !== null, fn ($q) => $q->whereIn('expenses.store_id', $storeIds))
            ->whereBetween('expenses.spent_at', [$period->from->toDateString(), $period->to->toDateString()]);
    }

    public function summary(?array $storeIds, Period $period): array
    {
        $byStatus = $this->orders($storeIds, $period)
            ->selectRaw('status, COUNT(*) as n, COALESCE(SUM(total), 0) as amount')
            ->groupBy('status')->get()->keyBy(fn ($r) => $r->status instanceof OrderStatus ? $r->status->value : $r->status);

        $count = fn (OrderStatus $s) => (int) ($byStatus[$s->value]->n ?? 0);
        $amount = fn (OrderStatus $s) => (int) ($byStatus[$s->value]->amount ?? 0);

        $valid = $this->orders($storeIds, $period)->valid();
        $revenue = (int) (clone $valid)->sum('total');
        $ordersCount = (int) (clone $valid)->count();
        $collected = (int) (clone $valid)->sum('amount_paid');
        $expenses = (int) $this->expenses($storeIds, $period)->sum('amount');

        $today = Order::query()
            ->when($storeIds !== null, fn ($q) => $q->whereIn('store_id', $storeIds))
            ->whereDate('ordered_at', now()->toDateString());

        return [
            'revenue' => $revenue,
            'orders_count' => $ordersCount,
            'average_basket' => $ordersCount ? intdiv($revenue, $ordersCount) : 0,
            'collected' => $collected,
            'expenses' => $expenses,
            'result' => $revenue - $expenses,
            'direct_sales' => (int) (clone $valid)->where('type', OrderType::Sale->value)->count(),
            'status_counts' => collect(OrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $count($s)])->all(),
            'preparing' => $count(OrderStatus::Preparing),
            'shipped' => $count(OrderStatus::Shipped),
            'delivered' => $count(OrderStatus::Delivered),
            'not_delivered' => $count(OrderStatus::NotDelivered),
            'cancelled' => $count(OrderStatus::Cancelled),
            'cancelled_amount' => $amount(OrderStatus::Cancelled),
            'returned' => $count(OrderStatus::Returned),
            'returned_amount' => $amount(OrderStatus::Returned),
            'today_orders' => (clone $today)->count(),
            'today_revenue' => (int) (clone $today)->valid()->sum('total'),
        ];
    }

    public function topProducts(?array $storeIds, Period $period, int $limit = 10): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.id', $this->orders($storeIds, $period)->valid()->select('orders.id'))
            ->selectRaw('order_items.product_id, MAX(order_items.product_name) as name, MAX(order_items.product_reference) as reference, SUM(order_items.quantity) as quantity, SUM(order_items.line_total) as revenue')
            ->groupBy('order_items.product_id')
            ->orderByDesc('quantity')
            ->limit($limit)
            ->get();
    }

    public function topCustomers(?array $storeIds, Period $period, int $limit = 10): Collection
    {
        $orders = $this->orders($storeIds, $period)->valid()->whereNotNull('customer_id')
            ->selectRaw('customer_id, COUNT(*) as orders_count, SUM(total) as total_spent, MAX(ordered_at) as last_order_at')
            ->groupBy('customer_id')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();
        $customers = Customer::whereIn('id', $orders->pluck('customer_id'))->get()->keyBy('id');

        return $orders->map(function ($row) use ($customers) {
            $row->customer = $customers[$row->customer_id] ?? null;

            return $row;
        })->filter(fn ($r) => $r->customer);
    }

    public function lowStock(?array $storeIds, int $limit = 15): Collection
    {
        return Stock::low()
            ->when($storeIds !== null, fn ($q) => $q->whereIn('stocks.store_id', $storeIds))
            ->with(['product', 'store'])
            ->orderBy('stocks.quantity')
            ->limit($limit)
            ->get();
    }

    /** Résultats par magasin : chiffre d'affaires, commandes, dépenses, résultat. */
    public function byStore(?array $storeIds, Period $period): Collection
    {
        $revenue = $this->orders($storeIds, $period)->valid()
            ->selectRaw('store_id, COUNT(*) as n, SUM(total) as amount')->groupBy('store_id')->get()->keyBy('store_id');
        $returns = $this->orders($storeIds, $period)->whereIn('status', [OrderStatus::Cancelled->value, OrderStatus::Returned->value])
            ->selectRaw('store_id, COUNT(*) as n')->groupBy('store_id')->pluck('n', 'store_id');
        $expenses = $this->expenses($storeIds, $period)
            ->selectRaw('store_id, SUM(amount) as amount')->groupBy('store_id')->pluck('amount', 'store_id');

        return Store::query()
            ->when($storeIds !== null, fn ($q) => $q->whereIn('id', $storeIds))
            ->orderBy('name')->get()
            ->map(function (Store $store) use ($revenue, $returns, $expenses) {
                $r = (int) ($revenue[$store->id]->amount ?? 0);
                $e = (int) ($expenses[$store->id] ?? 0);

                return (object) [
                    'store' => $store,
                    'orders' => (int) ($revenue[$store->id]->n ?? 0),
                    'revenue' => $r,
                    'cancelled_returned' => (int) ($returns[$store->id] ?? 0),
                    'expenses' => $e,
                    'result' => $r - $e,
                ];
            });
    }

    public function byCity(?array $storeIds, Period $period): Collection
    {
        return $this->byStore($storeIds, $period)
            ->groupBy(fn ($row) => $row->store->city)
            ->map(fn ($rows, $city) => (object) [
                'city' => $city,
                'stores' => $rows->count(),
                'orders' => $rows->sum('orders'),
                'revenue' => $rows->sum('revenue'),
                'expenses' => $rows->sum('expenses'),
                'result' => $rows->sum('result'),
            ])
            ->sortByDesc('revenue')
            ->values();
    }

    /** Série temporelle ventes / dépenses (par jour ou par mois). */
    public function timeline(?array $storeIds, Period $period): array
    {
        $sales = $this->orders($storeIds, $period)->valid()
            ->selectRaw('DATE(ordered_at) as d, SUM(total) as amount')->groupBy(DB::raw('DATE(ordered_at)'))->pluck('amount', 'd');
        $spent = $this->expenses($storeIds, $period)
            ->selectRaw('DATE(spent_at) as d, SUM(amount) as amount')->groupBy(DB::raw('DATE(spent_at)'))->pluck('amount', 'd');

        $monthly = $period->groupFormat() === 'month';
        $points = [];
        $cursor = $period->from->copy()->startOfDay();
        while ($cursor->lte($period->to)) {
            $key = $monthly ? $cursor->format('Y-m') : $cursor->toDateString();
            $points[$key] ??= ['label' => $monthly ? $cursor->translatedFormat('M Y') : $cursor->format('d/m'), 'sales' => 0, 'expenses' => 0];
            $points[$key]['sales'] += (int) ($sales[$cursor->toDateString()] ?? 0);
            $points[$key]['expenses'] += (int) ($spent[$cursor->toDateString()] ?? 0);
            $cursor->addDay();
        }

        return array_values($points);
    }

    public function expensesByCategory(?array $storeIds, Period $period): Collection
    {
        return $this->expenses($storeIds, $period)
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw('expense_categories.name as category, COUNT(*) as n, SUM(expenses.amount) as amount')
            ->groupBy('expense_categories.name')
            ->orderByDesc('amount')
            ->get();
    }
}
