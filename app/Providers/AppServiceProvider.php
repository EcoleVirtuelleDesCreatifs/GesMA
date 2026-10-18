<?php

namespace App\Providers;

use App\Models\SyncConflict;
use App\Models\User;
use App\Services\StatsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Sur l'hébergement mutualisé, le chemin interne (htdocs/<domaine>) pollue les URL
        // générées : on force la racine publique déclarée dans APP_URL.
        if (config('app.env') === 'production') {
            URL::forceRootUrl(config('app.url'));
            if (str_starts_with(config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }

        // Chaque droit de config/gesma.php devient une "ability" : @can('orders.create')
        foreach (array_keys(config('gesma.permissions', [])) as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        Paginator::defaultView('partials.pagination');

        RateLimiter::for('website', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by($request->ip().'|'.strtolower((string) $request->input('email'))));

        // Indicateurs du menu (stock faible, conflits)
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            if (! $user) {
                return;
            }
            $view->with('navCounters', [
                'low_stock' => $user->hasPermission('stock.view') ? app(StatsService::class)->lowStockCount($user) : 0,
                'conflicts' => $user->hasPermission('conflicts.view') ? SyncConflict::visibleTo($user)->open()->count() : 0,
            ]);
        });

        Blade::directive('money', fn ($expression) => "<?php echo e(money({$expression})); ?>");
    }
}
