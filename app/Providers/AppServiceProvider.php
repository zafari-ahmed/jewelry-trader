<?php

namespace App\Providers;

use App\Models\Location;
use App\Models\Setting;
use App\Models\User;
use App\Observers\AuditableObserver;
use Illuminate\Support\Facades\Blade;
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
        // Keeps layouts at resources/views/layouts/ (as CLAUDE.md Module 0c specifies)
        // while still resolving as <x-layouts.admin> slot components.
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        $this->bootAuditing();

    }

    /**
     * Rule 3.5: every state-changing action is logged, no role exempt.
     * Modules 3 and 4 add Payment, Product and Order to this list.
     */
    private function bootAuditing(): void
    {
        foreach ([Setting::class, Location::class, User::class] as $model) {
            $model::observe(AuditableObserver::class);
        }
    }
}
