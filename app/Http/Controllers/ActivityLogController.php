<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public const ACTIONS = [
        'stock.correction' => 'Corrections de stock',
        'stock.transfer' => 'Transferts de stock',
        'product.price' => 'Modifications de prix',
        'order.price_override' => 'Prix modifiés sur commande',
        'order.cancelled' => 'Annulations de commande',
        'order.returned' => 'Retours de commande',
        'invoice.cancelled' => 'Annulations de facture',
        'expense.update' => 'Modifications de dépense',
        'expense.delete' => 'Suppressions de dépense',
        'sync.conflict' => 'Conflits de synchronisation',
        'sync.resolve' => 'Résolutions de conflit',
        'website.stock_conflict' => 'Commandes site sans stock',
        'user.create' => 'Créations de compte',
        'user.update' => 'Modifications de compte',
        'auth.login' => 'Connexions',
    ];

    public function index(Request $request)
    {
        return view('admin.activity', [
            'logs' => ActivityLog::with(['user', 'store'])
                ->when($request->input('action'), fn ($q, $a) => $q->where('action', $a))
                ->when($request->input('user_id'), fn ($q, $u) => $q->where('user_id', $u))
                ->when($request->input('store_id'), fn ($q, $s) => $q->where('store_id', $s))
                ->when($request->input('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                ->when($request->input('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
                ->latest('id')->paginate(50)->withQueryString(),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'stores' => Store::orderBy('name')->get(),
            'actions' => self::ACTIONS,
        ]);
    }
}
