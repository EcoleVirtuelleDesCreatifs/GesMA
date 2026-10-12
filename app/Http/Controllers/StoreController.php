<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Création des magasins au fur et à mesure du développement de Mobilier Addict. */
class StoreController extends Controller
{
    public function index()
    {
        return view('admin.stores.index', [
            'stores' => Store::withCount(['users', 'orders'])->withSum('stocks', 'quantity')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.stores.form', ['store' => new Store(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $store = Store::create($this->validated($request));
        AuditLogger::log('store.create', 'Nouveau magasin : '.$store->name, $store);

        return redirect()->route('stores.index')->with('success', 'Magasin « '.$store->name.' » créé.');
    }

    public function edit(Store $store)
    {
        return view('admin.stores.form', ['store' => $store]);
    }

    public function update(Request $request, Store $store)
    {
        $store->update($this->validated($request, $store));

        return redirect()->route('stores.index')->with('success', 'Magasin mis à jour.');
    }

    private function validated(Request $request, ?Store $store = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'alpha_dash', Rule::unique('stores')->ignore($store?->id)],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
