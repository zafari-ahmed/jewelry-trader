<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Module 0c — static design screens
|--------------------------------------------------------------------------
| These render the converted design with placeholder data and no database.
| Later modules replace each view with its Livewire component behind the same
| route name, so navigation and partials do not have to change.
*/

Route::view('/', 'storefront.home')->name('shop.home');
Route::view('/catalog', 'storefront.catalog')->name('shop.catalog');
Route::view('/product', 'storefront.product')->name('shop.product');
Route::view('/checkout', 'storefront.checkout')->name('shop.checkout');
Route::view('/account', 'storefront.account')->name('shop.account');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.dashboard')->name('dashboard');
    Route::view('/inventory', 'admin.inventory')->name('inventory');
    Route::view('/inventory/create', 'admin.inventory-create')->name('inventory.create');
    Route::view('/review', 'admin.review')->name('review');
    Route::view('/settings', 'admin.settings.index')->name('settings');
    Route::view('/settings/payments', 'admin.settings.payments')->name('settings.payments');
    Route::view('/settings/ai', 'admin.settings.ai')->name('settings.ai');
    Route::view('/settings/flags', 'admin.settings.flags')->name('settings.flags');
    Route::view('/override', 'admin.override')->name('override');
    Route::view('/commission', 'admin.commission')->name('commission');
    Route::view('/audit', 'admin.audit')->name('audit');
    Route::view('/roles', 'admin.roles')->name('roles');
});

Route::prefix('pos')->name('pos.')->group(function () {
    Route::view('/', 'pos.sale')->name('sale');
    Route::view('/payment', 'pos.payment')->name('payment');
    Route::view('/return', 'pos.return')->name('return');
    Route::view('/receipt', 'pos.receipt')->name('receipt');
});
