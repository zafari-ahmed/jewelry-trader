<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Livewire\Customers;
use App\Livewire\Inventory;
use App\Livewire\Orders;
use App\Livewire\Pos;
use App\Livewire\Settings;
use App\Livewire\Shop;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public storefront (Module 7 replaces these with real components)
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    // Only listed, in-stock pieces are ever public (Module 7 acceptance).
    $listed = \App\Models\Product::query()->publiclyVisible()->with(['currentPricing', 'primaryImage']);

    return view('storefront.home', [
        'hero' => (clone $listed)->latest('id')->first(),
        'recent' => (clone $listed)->latest('id')->take(4)->get(),
        'periods' => (clone $listed)->whereNotNull('style_period')
            ->get()
            ->groupBy('style_period')
            ->map->count()
            ->sortDesc()
            ->take(4),
    ]);
})->name('shop.home');
Route::get('/collection', Shop\Catalog::class)->name('shop.catalog');
Route::get('/piece/{product}', Shop\ProductDetail::class)->name('shop.product');
Route::get('/bag', Shop\Bag::class)->name('shop.bag');
Route::get('/checkout', Shop\Checkout::class)->name('shop.checkout');
Route::get('/account', Shop\Account::class)->name('shop.account');
Route::get('/order/{order:order_number}', function (\App\Models\Order $order) {
    // A customer may only see their own order; a guest sees it once, from the
    // redirect that follows their payment.
    abort_unless(
        auth('customer')->id() === $order->customer_id || session()->pull('shop.just_ordered') === $order->order_number,
        404,
    );

    return view('shop.confirmation', ['order' => $order->load('items', 'customer')]);
})->name('shop.confirmation');

/*
|--------------------------------------------------------------------------
| Gateway webhooks — signed, stateless, no CSRF token
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/stripe', StripeWebhookController::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhooks.stripe');

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

Route::middleware('auth')->prefix('two-factor')->name('two-factor.')->group(function () {
    Route::get('/setup', \App\Livewire\Auth\TwoFactorSetup::class)->name('setup');
    Route::get('/challenge', \App\Livewire\Auth\TwoFactorChallenge::class)->name('challenge');
});

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
    Route::get('/inventory', Inventory\ProductList::class)->middleware('permission:view-products')->name('inventory');
    Route::get('/inventory/create', Inventory\ProductIntake::class)->middleware('permission:manage-products')->name('inventory.create');
    Route::get('/inventory/{product}/edit', Inventory\ProductIntake::class)->middleware('permission:view-products')->name('inventory.edit');
    Route::get('/customers', Customers\CustomerList::class)->middleware('permission:view-customers')->name('customers');
    Route::get('/orders', Orders\OrderList::class)->middleware('permission:view-orders')->name('orders');
    Route::get('/review', Inventory\ReviewQueue::class)->middleware('permission:view-products')->name('review');
    Route::view('/override', 'admin.override')->name('override');
    Route::view('/commission', 'admin.commission')->name('commission');
    Route::get('/audit', \App\Livewire\Admin\AuditLogViewer::class)->middleware('permission:view-audit-log')->name('audit');
    Route::get('/roles', \App\Livewire\Admin\RolesPermissions::class)->middleware('permission:manage-roles')->name('roles');

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
            Route::get('/field-rules', Settings\FieldRules::class)->middleware('permission:manage-field-rules')->name('.field-rules');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Point of Sale (Module 6 replaces these with Livewire components)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:use-pos'])->prefix('pos')->name('pos.')->group(function () {
    Route::get('/', Pos\Register::class)->name('sale');
    Route::get('/returns', Pos\Returns::class)->middleware('permission:process-refunds')->name('returns');
    Route::get('/receipt/{order}', function (\App\Models\Order $order) {
        abort_unless(auth()->user()->can('view', $order), 403);

        return view('pos.receipt-print', ['order' => $order->load(['items', 'payments.splits', 'location', 'createdBy'])]);
    })->name('receipt.print');
});
