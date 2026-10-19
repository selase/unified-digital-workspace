<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

// Use the standard Pest setup
uses()->group('admin', 'tenant');

test('updating tenant handles null allowed_ips gracefully', function () {
    Artisan::call('db:seed', ['--class' => 'RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'PermissionsSeeder']);

    $admin = User::factory()->create();
    $admin->assignRole('Superadmin');

    $tenant = Tenant::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'slug' => 'original',
    ]);

    // Simulate ConvertEmptyStringsToNull middleware effect by sending null for allowed_ips
    $response = $this->actingAs($admin)->put(route('tenants.update', $tenant->uuid), [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'phone_number' => '1234567890',
        'address' => '123 Updated St',
        'country' => 'USA',
        'city' => 'Updated City',
        'state_or_region' => 'Updated State',
        'zipcode' => '54321',
        'status' => 'active',
        'subdomain' => 'updated',
        'isolation_mode' => 'shared',
        'db_driver' => 'pgsql',
        'allowed_ips' => null, // The critical NULL value
    ]);

    $response->assertSessionHas('status', 'success');
    $response->assertRedirect(route('tenants.index'));

    $tenant->refresh();
    expect($tenant->name)->toBe('Updated Name');
    // Expect allowed_ips to be [''] because explode(',', '') results in ['']
    // And array_map('trim', ['']) is ['']
    expect($tenant->allowed_ips)->toBe(['']);
});

test('isolation mode and database driver cannot be changed on an existing tenant', function () {
    Artisan::call('db:seed', ['--class' => 'RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'PermissionsSeeder']);

    $admin = User::factory()->create();
    $admin->assignRole('Superadmin');

    $tenant = Tenant::factory()->create(['slug' => 'immutable-mode', 'isolation_mode' => 'shared']);

    $payload = [
        'name' => 'Immutable Mode',
        'email' => 'immutable@example.com',
        'phone_number' => '1234567890',
        'address' => '1 Fixed St',
        'country' => 'USA',
        'city' => 'City',
        'state_or_region' => 'State',
        'zipcode' => '54321',
        'status' => 'active',
        'subdomain' => 'immutable-mode',
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'pgsql',
    ];

    $this->actingAs($admin)
        ->put(route('tenants.update', $tenant->uuid), $payload)
        ->assertSessionHasErrors('isolation_mode');

    $tenant->refresh();
    expect($tenant->isolation_mode)->toBe('shared');
    expect($tenant->meta['database'] ?? null)->toBeNull();
});
