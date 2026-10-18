<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie un droit défini dans config/gesma.php.
 * Usage : ->middleware('role:orders.create')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        if (! $user || ! $user->is_active) {
            abort(403, 'Compte inactif.');
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'Vous n’avez pas accès à cette fonctionnalité.');
    }
}
