<?php

use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Http\Middleware\AuthenticateApiClient;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [EnsureUserIsActive::class]);
        $middleware->alias(['api.client' => AuthenticateApiClient::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Les règles de gestion non respectées reviennent au formulaire avec un message clair.
        $exceptions->render(function (BusinessRuleException|InsufficientStockException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'shortages' => $e instanceof InsufficientStockException ? $e->shortages : null,
                ], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });
    })->create();
