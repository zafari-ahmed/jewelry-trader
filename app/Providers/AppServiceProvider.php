<?php

namespace App\Providers;

use App\Models\Location;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Observers\AuditableObserver;
use App\Services\Payments\Stripe\StripeApi;
use App\Services\Payments\Stripe\StripeApiClient;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The only place the Stripe SDK is bound; tests swap this for a double.
        $this->app->bind(StripeApi::class, StripeApiClient::class);
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
     * Module 4 adds Product and Order to this list.
     */
    private function bootAuditing(): void
    {
        foreach ([Setting::class, Location::class, User::class, Payment::class] as $model) {
            $model::observe(AuditableObserver::class);
        }
    }
}
