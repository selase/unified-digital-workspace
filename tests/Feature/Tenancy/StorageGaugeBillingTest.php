<?php

declare(strict_types=1);

use App\Enum\UsageMetric;
use App\Models\UsageRollup;
use App\Services\Tenancy\TenantStorageManager;
use App\Services\Tenancy\UsageService;
use Illuminate\Support\Facades\Storage;

it('does not multiply storage charges when the audit runs twice on the same day', function (): void {
    $tenant = setActiveTenantForTest();
    Storage::fake('s3');
    app(TenantStorageManager::class)->configure($tenant);
    Storage::disk('tenant')->put('document.txt', str_repeat('a', 1024));
    $this->artisan('tenants:audit-storage')->assertSuccessful();
    Storage::disk('tenant')->put('document.txt', str_repeat('a', 2048));
    $this->artisan('tenants:audit-storage')->assertSuccessful();
    expect((float) UsageRollup::query()->where('tenant_id', $tenant->id)
        ->where('metric', UsageMetric::STORAGE_BYTES)->sole()->value)->toBe(2048.0);
});

it('retains a distinct monthly active user meter after raw event pruning', function (): void {
    $tenant = setActiveTenantForTest();
    $usage = app(UsageService::class);
    $this->travelTo(now()->startOfMonth()->addDay());
    $usage->recordActiveUser($tenant, 'member-1');
    $usage->recordActiveUser($tenant, 'member-1');
    $usage->recordActiveUser($tenant, 'member-2');
    $this->travel(12)->days();
    $usage->recordActiveUser($tenant, 'member-1');
    $this->artisan('usage:prune')->assertSuccessful();
    expect((float) UsageRollup::query()->where('tenant_id', $tenant->id)
        ->where('metric', UsageMetric::USER_ACTIVE_MONTHLY)->where('period', 'month')->sum('value'))->toBe(2.0);
});

it('records database footprint as a snapshot when the database audit is repeated', function (): void {
    $user = App\Models\User::factory()->create();
    $tenant = setActiveTenantForTest($user);
    $expected = app(App\Services\Tenancy\DatabaseMeter::class)->calculateUsage($tenant);
    $this->artisan('tenants:audit-db')->assertSuccessful();
    $this->artisan('tenants:audit-db')->assertSuccessful();
    expect((float) UsageRollup::query()->where('tenant_id', $tenant->id)
        ->where('metric', UsageMetric::DB_BYTES)->sole()->value)->toBe((float) $expected);
});
