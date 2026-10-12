<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function user(): User
    {
        return auth()->user();
    }

    /** Interdit l'accès aux données d'un autre magasin. */
    protected function authorizeStore(Model|int|null $subject): void
    {
        $storeId = $subject instanceof Model ? $subject->getAttribute('store_id') : $subject;
        abort_unless($this->user()->canAccessStore($storeId), 403, "Vous n'avez pas accès aux données de ce magasin.");
    }

    protected function authorizeAny(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            if ($this->user()->hasPermission($permission)) {
                return;
            }
        }
        abort(403);
    }

    /** Magasin sélectionné dans les filtres (limité au magasin de l'utilisateur hors direction). */
    protected function selectedStoreId(Request $request): ?int
    {
        $user = $this->user();
        if (! $user->isDirection()) {
            return $user->store_id;
        }

        return $request->integer('store_id') ?: null;
    }

    /** Magasin par défaut pour une création. */
    protected function storeIdForCreation(Request $request): int
    {
        $user = $this->user();
        $storeId = $user->isDirection() ? (int) $request->input('store_id') : (int) $user->store_id;
        abort_unless($storeId && $user->canAccessStore($storeId), 422, 'Veuillez choisir un magasin.');

        return $storeId;
    }
}
