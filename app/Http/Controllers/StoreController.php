<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Store;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Gestion des magasins (Direction) */
class StoreController extends Controller
{
    public function index()
    {
        return view('stores.index', [
            'stores' => Store::withCount(['users', 'orders'])->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('stores.form', ['store' => new Store(['is_active' => true])]);
    }

    public function store(Request $request, StockService $stock)
    {
        $store = Store::create($this->validated($request));
        $stock->ensureStockRows(null, $store);
        ActivityLog::record('magasin.creation', "Création du magasin {$store->name} ({$store->city})", $store, [], $store->id);

        return redirect()->route('stores.index')->with('success', "Magasin {$store->name} créé.");
    }

    public function edit(Store $store)
    {
        return view('stores.form', compact('store'));
    }

    public function update(Request $request, Store $store)
    {
        $before = $store->only('code', 'name', 'city', 'is_active');
        $store->update($this->validated($request, $store));
        ActivityLog::record('magasin.modification', "Modification du magasin {$store->name}", $store, ['avant' => $before, 'apres' => $store->only(array_keys($before))], $store->id);

        return redirect()->route('stores.index')->with('success', 'Magasin mis à jour.');
    }

    private function validated(Request $request, ?Store $store = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:10', Rule::unique('stores')->ignore($store)],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
