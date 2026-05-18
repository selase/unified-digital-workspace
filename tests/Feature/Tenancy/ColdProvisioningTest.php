<?php

declare(strict_types=1);

use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    refreshTenantDatabases();
    Artisan::call('db:seed', ['--class' => 'RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'PermissionsSeeder']);
    Artisan::call('db:seed', ['--class' => 'PackageSeeder']);
    $this->dedicatedTenantDbsToDrop = [];
});

afterEach(function () {
    foreach ($this->dedicatedTenantDbsToDrop ?? [] as $dbName) {
        dropTenantDatabase($dbName);
    }
});

test('cold provisioning creates a functional tenant with dedicated DB and synced features', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Superadmin');

    $package = Package::where('slug', 'pro')->first();

    $payload = [
        'name' => 'Cold Provisioned Tenant',
        'email' => 'cold@example.com',
        'phone_number' => '1234567890',
        'address' => '456 Cold Ave',
        'country' => 'USA',
        'city' => 'Cold City',
        'state_or_region' => 'Cold State',
        'zipcode' => '99999',
        'status' => 'active',
        'subdomain' => 'coldstart',
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'pgsql',
        'package_id' => $package->id,
    ];

    $response = $this->actingAs($admin)->post(route('tenants.store'), $payload);

    $response->assertRedirect(route('tenants.index'));

    // 1. Verify Tenant Record
    $tenant = Tenant::where('slug', 'coldstart')->first();
    expect($tenant)->not->toBeNull();
    expect($tenant->isolation_mode)->toBe('db_per_tenant');

    // 2. Verify Dedicated postgres database was created
    $dbName = $tenant->meta['database'];
    $this->dedicatedTenantDbsToDrop[] = $dbName;

    $exists = DB::connection('landlord')->select(
        'SELECT 1 FROM pg_database WHERE datname = ?',
        [$dbName]
    );
    expect($exists)->not->toBeEmpty();

    // 3. Verify Features were synced from Package
    // Package 'pro' has 'analytics' and 'priority-support' based on PackageSeeder
    expect($tenant->features()->where('feature_key', 'analytics')->exists())->toBeTrue();
    expect($tenant->features()->where('feature_key', 'priority-support')->exists())->toBeTrue();

    // 4. Verify resolution works
    $baseDomain = mb_ltrim((string) config('session.domain'), '.');
    $subdomainHost = "coldstart.{$baseDomain}";

    // Switch context using the middleware (simulated by helper)
    $this->get("http://{$subdomainHost}/dashboard", ['HTTP_HOST' => $subdomainHost])
        ->assertStatus(200);

    expect(app(App\Services\Tenancy\TenantContext::class)->getTenant()->id)->toBe($tenant->id);
});
