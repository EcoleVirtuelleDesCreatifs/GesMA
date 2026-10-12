<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Services\StockService;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function index()
    {
        return view('transfers.index', [
            'transfers' => StockTransfer::with(['fromStore', 'toStore', 'user'])->withSum('items', 'quantity')
                ->visibleTo($this->user())->latest('transferred_at')->paginate(25),
        ]);
    }

    public function create()
    {
        return view('transfers.create', [
            'fromStores' => $this->user()->accessibleStores(),
            'toStores' => Store::active()->orderBy('name')->get(),
            'products' => Product::with('stocks')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'from_store_id' => ['required', 'integer', 'exists:stores,id'],
            'to_store_id' => ['required', 'integer', 'exists:stores,id', 'different:from_store_id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
        $this->authorizeStore((int) $data['from_store_id']);

        $transfer = $stock->transfer((int) $data['from_store_id'], (int) $data['to_store_id'], $data['items'], $this->user(), $data['notes'] ?? null);

        return redirect()->route('transfers.show', $transfer)->with('success', 'Transfert '.$transfer->number.' effectué. Les stocks des deux magasins sont à jour.');
    }

    public function show(StockTransfer $transfer)
    {
        $user = $this->user();
        abort_unless($user->canAccessStore($transfer->from_store_id) || $user->canAccessStore($transfer->to_store_id), 403);

        return view('transfers.show', ['transfer' => $transfer->load(['fromStore', 'toStore', 'user', 'items.product'])]);
    }
}
