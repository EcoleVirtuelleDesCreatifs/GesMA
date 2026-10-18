<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

abstract class Controller
{
    /** Filtre magasin choisi par la Direction (null = tous les magasins) */
    protected function storeFilter(Request $request): ?int
    {
        $user = $request->user();
        if (! $user->isDirection()) {
            return $user->store_id;
        }
        $id = (int) $request->input('store_id');

        return $id > 0 ? $id : null;
    }

    /** Magasins que l'utilisateur peut choisir dans les formulaires */
    protected function selectableStores(Request $request): Collection
    {
        return Store::active()->whereIn('id', $request->user()->accessibleStoreIds())->orderBy('name')->get();
    }

    /** Refuse l'accès à une donnée d'un autre magasin */
    protected function authorizeStore(Request $request, Model|int|null $modelOrStoreId): void
    {
        $storeId = $modelOrStoreId instanceof Model ? $modelOrStoreId->store_id : $modelOrStoreId;
        abort_unless($request->user()->canAccessStore($storeId ? (int) $storeId : null), 403, 'Cette donnée appartient à un autre magasin.');
    }

    /** Magasin à utiliser pour une création (choisi par la Direction, imposé sinon) */
    protected function storeForCreation(Request $request): int
    {
        $user = $request->user();
        $storeId = $user->isDirection() ? (int) $request->input('store_id') : (int) $user->store_id;
        abort_unless($storeId && $user->canAccessStore($storeId), 422, 'Choisissez un magasin valide.');

        return $storeId;
    }
}
