<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\OrgSettingsController;
use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\UserController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth', '2fa_challenge', 'onboarding']], function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('tenant.dashboard');

    Route::get('/billing', [App\Http\Controllers\Billing\BillingController::class, 'index'])
        ->name('tenant.billing.index')
        ->middleware('can:core.settings.manage');
    Route::get('/pricing', [App\Http\Controllers\Billing\BillingController::class, 'pricing'])
        ->name('tenant.pricing')
        ->middleware('can:core.settings.manage');
    Route::post('/billing/checkout', [App\Http\Controllers\Billing\CheckoutController::class, 'store'])
        ->name('tenant.billing.checkout')
        ->middleware('can:core.settings.manage');

    Route::get('/settings', [OrgSettingsController::class, 'index'])
        ->name('tenant.settings.index');
    Route::post('/settings', [OrgSettingsController::class, 'update'])
        ->name('tenant.settings.update');
    Route::post('/settings/verify-domain', [OrgSettingsController::class, 'verifyDomain'])
        ->name('tenant.settings.verify-domain');

    Route::get('/settings/billing', [App\Http\Controllers\Tenant\BillingSettingsController::class, 'index'])
        ->name('tenant.settings.billing')
        ->middleware('can:core.settings.manage');
    Route::post('/settings/billing', [App\Http\Controllers\Tenant\BillingSettingsController::class, 'update'])
        ->name('tenant.settings.billing.update')
        ->middleware('can:core.settings.manage');

    Route::get('/settings/payments', [App\Http\Controllers\Tenant\PaymentSettingsController::class, 'index'])
        ->name('tenant.settings.payments.index');
    Route::post('/settings/payments', [App\Http\Controllers\Tenant\PaymentSettingsController::class, 'update'])
        ->name('tenant.settings.payments.update');

    Route::post('users/all', [UserController::class, 'getAllUsers'])->name('tenant.users.all');
    Route::resource('users', UserController::class)->except(['create', 'show', 'destroy'])->names('tenant.users');
    Route::resource('roles', RoleController::class)->names('tenant.roles');
    Route::resource('api-keys', App\Http\Controllers\Tenant\ApiKeyController::class)
        ->names('tenant.api-keys')
        ->only(['index', 'store', 'destroy']);
    Route::resource('llm-usage', App\Http\Controllers\Tenant\LlmUsageController::class)
        ->names('tenant.llm-usage')
        ->only(['index'])
        ->middleware('can:core.settings.manage');

    // Module catalog — tenant admins enable/disable optional modules subject to
    // package entitlements (handled by ModuleManager::checkEntitlements).
    Route::get('modules', [App\Http\Controllers\Tenant\ModuleCatalogController::class, 'index'])
        ->name('tenant.modules.index');
    Route::post('modules/{slug}/enable', [App\Http\Controllers\Tenant\ModuleCatalogController::class, 'enable'])
        ->name('tenant.modules.enable');
    Route::post('modules/{slug}/disable', [App\Http\Controllers\Tenant\ModuleCatalogController::class, 'disable'])
        ->name('tenant.modules.disable');

    // Merchant Finance & Sales
    Route::get('/finance', [App\Http\Controllers\Tenant\FinanceController::class, 'index'])->name('tenant.finance.index')->middleware('can:core.settings.manage');
    Route::post('/finance/refund/{transaction}', [App\Http\Controllers\Tenant\FinanceController::class, 'refund'])->name('tenant.finance.refund')->middleware('can:core.settings.manage');

    Route::prefix('llm-config')->name('tenant.llm-config.')->middleware('can:core.settings.manage')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\LlmConfigController::class, 'index'])->name('index');
        Route::put('/', [App\Http\Controllers\Tenant\LlmConfigController::class, 'update'])->name('update');
        Route::delete('/{provider}', [App\Http\Controllers\Tenant\LlmConfigController::class, 'destroy'])->name('destroy');
    });

    // Onboarding Wizard (duplicated for subdomains)
    Route::group(['prefix' => 'onboarding', 'as' => 'tenant.onboarding.'], function () {
        Route::get('wizard', [App\Http\Controllers\Admin\OnboardingController::class, 'index'])->name('wizard');
        Route::post('branding', [App\Http\Controllers\Admin\OnboardingController::class, 'updateBranding'])->name('branding.update');
        Route::post('finish', [App\Http\Controllers\Admin\OnboardingController::class, 'finish'])->name('finish');
    });

    // LLM Billing
    Route::post('/billing/llm-checkout', [App\Http\Controllers\Tenant\LlmCheckoutController::class, 'store'])
        ->name('billing.llm-checkout')
        ->middleware('can:core.settings.manage');
});
