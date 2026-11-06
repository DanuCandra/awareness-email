<?php

namespace App\Providers;

use App\WorkspaceSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DevCommands::artisan('queue:work database --queue=simulations --sleep=1 --timeout=60', 'simulations');
        DevCommands::artisan('schedule:work', 'scheduler');
        RateLimiter::for('simulation-links', fn (Request $request): Limit => Limit::perMinute(60)->by(hash('sha256', $request->route('token').'|'.$request->ip())));
        View::composer('layouts.partials.scripts', function (\Illuminate\View\View $view): void {
            if (auth()->check()) {
                $view->with('workspaceSettings', app(WorkspaceSettings::class)->all());
            }
        });
    }
}
