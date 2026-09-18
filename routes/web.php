<?php

use App\Http\Controllers\Auth\LoginController;
use App\Livewire\Settings;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public storefront (Module 7 replaces these with real components)
|--------------------------------------------------------------------------
*/
Route::view('/', 'storefront.home')->name('shop.home');
Route::view('/catalog', 'storefront.catalog')->name('shop.catalog');
Route::view('/product', 'storefront.product')->name('shop.product');
Route::view('/checkout', 'storefront.checkout')->name('shop.checkout');
Route::view('/account', 'storefront.account')->name('shop.account');

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
| Rule 3.6: every route carries its permission, and each Livewire component
| re-checks on mount and on save. The middleware is the outer gate, not the
| only one.
*/
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.dashboard')->name('dashboard');
    Route::view('/inventory', 'admin.inventory')->name('inventory');
    Route::view('/inventory/create', 'admin.inventory-create')->name('inventory.create');
    Route::view('/review', 'admin.review')->name('review');
    Route::view('/override', 'admin.override')->name('override');
    Route::view('/commission', 'admin.commission')->name('commission');
    Route::view('/audit', 'admin.audit')->name('audit');
    Route::view('/roles', 'admin.roles')->name('roles');

    Route::prefix('settings')->name('settings')->group(function () {
        Route::view('/', 'admin.settings.index')->middleware('permission:manage-settings|view-settings');

        Route::middleware('permission:manage-settings')->group(function () {
            Route::get('/general', Settings\General::class)->name('.general');
            Route::get('/locations', Settings\Locations::class)->middleware('permission:manage-locations')->name('.locations');
            Route::get('/payments', Settings\Payments::class)->middleware('permission:manage-payments-config')->name('.payments');
            Route::get('/ai', Settings\Ai::class)->middleware('permission:manage-ai-config')->name('.ai');
            Route::get('/security', Settings\Security::class)->middleware('permission:manage-security-config')->name('.security');
            Route::get('/commission', Settings\Commission::class)->middleware('permission:manage-commission-config')->name('.commission');
            Route::get('/flags', Settings\FeatureFlags::class)->middleware('permission:manage-feature-flags')->name('.flags');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Point of Sale (Module 6 replaces these with Livewire components)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('pos')->name('pos.')->group(function () {
    Route::view('/', 'pos.sale')->name('sale');
    Route::view('/payment', 'pos.payment')->name('payment');
    Route::view('/return', 'pos.return')->name('return');
    Route::view('/receipt', 'pos.receipt')->name('receipt');
});
