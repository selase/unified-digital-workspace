<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\Memberships\Models\MemType;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoManifest;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoSections;
use App\Modules\SiteThyroidGhanaFoundation\Demo\Sections\MembershipTiersSection;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;

function tgfTierTenant(): Tenant
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user, ['slug' => 'thyroid-ghana-foundation']);
    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);
    app(TenantDatabaseManager::class)->configure($tenant);
    foreach (['finance', 'memberships'] as $slug) {
        TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-'.$slug, 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
        app(ModuleManager::class)->enableForTenant($slug, $tenant);
    }
    foreach (['Finance', 'Memberships'] as $module) {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path("Modules/{$module}/Database/Migrations"), '--realpath' => true, '--force' => true]);
    }
    app()->instance(DemoSections::class, new DemoSections([]));

    return $tenant;
}

describe('Proposed membership tiers', function (): void {
    test('creates three public tiers starting at GHS 100 with no Demo prefix', function (): void {
        $tenant = tgfTierTenant();

        $ids = (new MembershipTiersSection)->ensure($tenant);

        $types = MemType::query()->where('tenant_id', $tenant->id)->orderBy('fee')->get();
        expect($ids)->toHaveCount(3)
            ->and($types->pluck('name')->all())->toBe(['Bronze Membership', 'Silver Membership', 'Gold Membership'])
            ->and($types->pluck('fee')->all())->toBe([10_000, 20_000, 200_000])
            ->and($types->every(fn (MemType $t): bool => $t->is_public && $t->is_active && $t->period_months === 12))->toBeTrue();
    });

    test('creates nothing a second time', function (): void {
        $tenant = tgfTierTenant();
        $section = new MembershipTiersSection;
        $section->ensure($tenant);

        expect($section->ensure($tenant))->toBe([])
            ->and(MemType::query()->where('tenant_id', $tenant->id)->count())->toBe(3);
    });

    test('creates nothing when the tenant already has a membership type of its own', function (): void {
        $tenant = tgfTierTenant();
        MemType::query()->create(['tenant_id' => $tenant->id, 'name' => 'Founding member', 'fee' => 50_000, 'period_months' => 12]);

        expect((new MembershipTiersSection)->ensure($tenant))->toBe([])
            ->and(MemType::query()->where('tenant_id', $tenant->id)->count())->toBe(1);
    });

    test('does nothing when the Memberships module is not set up', function (): void {
        $user = User::factory()->create();
        $tenant = setActiveTenantForTest($user, ['slug' => 'thyroid-ghana-foundation']);
        app(TenantDatabaseManager::class)->configure($tenant);
        DB::connection('tenant')->getSchemaBuilder()->dropIfExists('mem_renewals');
        DB::connection('tenant')->getSchemaBuilder()->dropIfExists('mem_members');
        DB::connection('tenant')->getSchemaBuilder()->dropIfExists('mem_types');

        expect((new MembershipTiersSection)->ensure($tenant))->toBe([]);
    });
});

describe('Tiers and the demo commands', function (): void {
    test('seeding creates the tiers, removal keeps them, and --with-tiers removes them later', function (): void {
        $tenant = tgfTierTenant();

        Artisan::call('tgf:demo-seed', ['--confirm' => true]);
        expect(MemType::query()->where('tenant_id', $tenant->id)->count())->toBe(3)
            ->and(app(DemoManifest::class)->proposedTierIds($tenant))->toHaveCount(3);

        Artisan::call('tgf:demo-remove', ['--confirm' => true]);
        expect(MemType::query()->where('tenant_id', $tenant->id)->count())->toBe(3)
            ->and(app(DemoManifest::class)->proposedTierIds($tenant))->toHaveCount(3);

        $code = Artisan::call('tgf:demo-remove', ['--confirm' => true, '--with-tiers' => true]);
        expect($code)->toBe(0)
            ->and(MemType::query()->where('tenant_id', $tenant->id)->count())->toBe(0)
            ->and(app(DemoManifest::class)->proposedTierIds($tenant))->toBe([]);
    });

    test('seeding again after a removal does not duplicate the tiers', function (): void {
        $tenant = tgfTierTenant();

        Artisan::call('tgf:demo-seed', ['--confirm' => true]);
        Artisan::call('tgf:demo-remove', ['--confirm' => true]);
        Artisan::call('tgf:demo-seed', ['--confirm' => true]);

        expect(MemType::query()->where('tenant_id', $tenant->id)->count())->toBe(3)
            ->and(app(DemoManifest::class)->proposedTierIds($tenant))->toHaveCount(3);
    });

    test('--with-tiers keeps a tier that a real member already joined', function (): void {
        $tenant = tgfTierTenant();
        Artisan::call('tgf:demo-seed', ['--confirm' => true]);
        Artisan::call('tgf:demo-remove', ['--confirm' => true]);
        $type = MemType::query()->where('tenant_id', $tenant->id)->where('name', 'Silver Membership')->firstOrFail();
        app(App\Modules\Memberships\Services\MembershipService::class)->join($tenant->id, $type, 'Real Member', ['email' => 'real@example.com']);
        Artisan::call('tgf:demo-remove', ['--confirm' => true, '--with-tiers' => true]);

        expect(MemType::query()->where('tenant_id', $tenant->id)->pluck('name')->all())->toBe(['Silver Membership']);
    });
});
