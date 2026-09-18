<?php

namespace App\Providers;

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

        //
    }
}
