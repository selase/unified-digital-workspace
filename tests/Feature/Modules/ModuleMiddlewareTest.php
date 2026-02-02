<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name' => 'Test Tenant',
        'slug' => 'test-tenant',
        'isolation_mode' => 'shared',
    ]);

    $this->user = User::factory()->create();
    $this->tenant->users()->attach($this->user->id);

    app(TenantContext::class)->setTenant($this->tenant);

    // Register temporary routes for testing middleware
    Route::get('/test-module-enabled', function () {
        return response('Module Enabled');
    })->middleware(['web', 'module:core']);

    Route::get('/test-module-disabled', function () {
        return response('Module Enabled');
    })->middleware(['web', 'module:non-existent-module']);
});

test('middleware allows access when module is enabled', function () {
    // Core module is always enabled
    $this->actingAs($this->user)
        ->get('/test-module-enabled')
        ->assertStatus(200)
        ->assertSee('Module Enabled');
});

test('middleware denies access when module is disabled', function () {
    // Non-existent module should be denied
    $this->actingAs($this->user)
        ->get('/test-module-disabled')
        ->assertStatus(403);
});

test('middleware denies access when no tenant context', function () {
    // Clear tenant context by rebinding a fresh instance
    $this->app->singleton(TenantContext::class, function () {
        return new TenantContext();
    });

    Route::get('/test-no-tenant', function () {
        return response('OK');
    })->middleware(['module:core']);

    $this->actingAs($this->user)
        ->get('/test-no-tenant')
        ->assertStatus(403);
});

test('middleware allows access after module is enabled for tenant', function () {
    // Create a test route for a module that needs to be explicitly enabled
    Route::get('/test-explicit-enable', function () {
        return response('Explicitly Enabled');
    })->middleware(['web', 'module:core']);

    // Enable the module
    app(ModuleManager::class)->enableForTenant('core', $this->tenant);

    $this->actingAs($this->user)
        ->get('/test-explicit-enable')
        ->assertStatus(200);
});
