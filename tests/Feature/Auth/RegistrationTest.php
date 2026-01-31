<?php

declare(strict_types=1);

use App\Providers\RouteServiceProvider;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

beforeEach(function () {
    refreshTenantDatabases();

    Location::shouldReceive('get')->andReturn(
        Position::make(['countryName' => 'Testland'])
    );
});

test('registration screen can be rendered', function () {
    $tenant = setActiveTenantForTest();

    $this->withSession(['active_tenant_id' => $tenant->id])
        ->get('/register')
        ->assertStatus(200);
});

test('new users can register', function () {
    $tenant = setActiveTenantForTest();

    $response = $this->withSession(['active_tenant_id' => $tenant->id])
        ->withoutMiddleware(App\Http\Middleware\VerifyCsrfToken::class)
        ->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $response->assertRedirect(RouteServiceProvider::HOME);
});
