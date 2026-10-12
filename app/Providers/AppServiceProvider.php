<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));
        Paginator::defaultView('partials.pagination');

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Les droits sont définis par profil dans App\Enums\Role::permissions().
        Gate::before(fn (User $user, string $ability) => $user->hasPermission($ability) ? true : null);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('website-api', fn (Request $request) => Limit::perMinute(120)->by($request->bearerToken() ?: $request->ip()));
        RateLimiter::for('sync', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
