<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\EnsureRole;
use Illuminate\Http\Request;
use App\Http\Middleware\VerifyWebsiteApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'website.key' => VerifyWebsiteApiKey::class,
        ]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Les erreurs métier (stock insuffisant, statut invalide...) sont affichées à l'utilisateur
        $exceptions->render(function (BusinessException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->withErrors(['business' => $e->getMessage()]);
        });
    })->create();
