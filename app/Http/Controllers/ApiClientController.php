<?php

namespace App\Http\Controllers;

use App\Models\ApiClient;
use App\Models\Store;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

/** Jetons d'accès du site web Mobilier Addict à l'API de GesMA. */
class ApiClientController extends Controller
{
    public function index()
    {
        return view('admin.api-clients', [
            'clients' => ApiClient::with('defaultStore')->latest()->get(),
            'stores' => Store::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'default_store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ]);
        [$client, $token] = ApiClient::issue($data['name'], $data['default_store_id'] ?? null);
        AuditLogger::log('api.token', 'Jeton API créé : '.$client->name, $client);

        return back()->with('new_token', $token)->with('success', 'Jeton créé. Copiez-le maintenant : il ne sera plus affiché.');
    }

    public function toggle(ApiClient $apiClient)
    {
        $apiClient->update(['is_active' => ! $apiClient->is_active]);
        AuditLogger::log('api.token', 'Jeton API '.($apiClient->is_active ? 'réactivé' : 'désactivé').' : '.$apiClient->name, $apiClient);

        return back()->with('success', 'Jeton '.($apiClient->is_active ? 'réactivé' : 'désactivé').'.');
    }
}
