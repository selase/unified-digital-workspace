<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\CmsCore\Models\Setting;
use App\Modules\Finance\Models\FinContact;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoManifest;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoSections;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;

function tgfDemoTenant(): Tenant
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user, ['slug' => 'thyroid-ghana-foundation']);
    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);
    app(TenantDatabaseManager::class)->configure($tenant);
    TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-finance', 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
    app(ModuleManager::class)->enableForTenant('finance', $tenant);
    Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path('Modules/Finance/Database/Migrations'), '--realpath' => true, '--force' => true]);

    return $tenant;
}

function tgfFakeSection(): DemoSection
{
    return new class implements DemoSection
    {
        public function label(): string
        {
            return 'fake';
        }

        public function seed(Tenant $tenant): void
        {
            Setting::query()->create(['tenant_id' => $tenant->id, 'group' => 'tgf-fake', 'key' => 'row', 'value' => ['label' => 'Demo: fake']]);
        }

        public function remove(Tenant $tenant, CarbonImmutable $since): int
        {
            return Setting::query()->where('tenant_id', $tenant->id)->where('group', 'tgf-fake')->where('created_at', '>=', $since)->delete();
        }
    };
}

function tgfFakeRows(Tenant $tenant): int
{
    return Setting::query()->where('tenant_id', $tenant->id)->where('group', 'tgf-fake')->count();
}

beforeEach(function (): void {
    app()->instance(DemoSections::class, new DemoSections([tgfFakeSection()]));
});

describe('tgf:demo-seed', function (): void {
    test('refuses to run on production without --confirm and writes nothing', function (): void {
        $tenant = tgfDemoTenant();
        app()->detectEnvironment(fn (): string => 'production');

        $code = Artisan::call('tgf:demo-seed');
        app()->detectEnvironment(fn (): string => 'testing');

        expect($code)->not->toBe(0)
            ->and(tgfFakeRows($tenant))->toBe(0)
            ->and(app(DemoManifest::class)->startedAt($tenant))->toBeNull();
    });

    test('refuses any tenant other than thyroid-ghana-foundation', function (): void {
        $tenant = tgfDemoTenant();
        $other = Tenant::factory()->create(['slug' => 'someone-else', 'isolation_mode' => 'shared']);

        $code = Artisan::call('tgf:demo-seed', ['--tenant' => $other->slug, '--confirm' => true]);

        expect($code)->not->toBe(0)
            ->and(tgfFakeRows($tenant))->toBe(0)
            ->and(tgfFakeRows($other))->toBe(0);
    });

    test('seeding twice does not duplicate anything', function (): void {
        $tenant = tgfDemoTenant();

        Artisan::call('tgf:demo-seed', ['--confirm' => true]);
        $code = Artisan::call('tgf:demo-seed', ['--confirm' => true]);

        expect($code)->toBe(0)->and(tgfFakeRows($tenant))->toBe(1);
    });

    test('sends no mail and no notification', function (): void {
        Mail::fake();
        Notification::fake();
        tgfDemoTenant();

        Artisan::call('tgf:demo-seed', ['--confirm' => true]);

        Mail::assertNothingSent();
        Notification::assertNothingSent();
    });
});

describe('tgf:demo-remove', function (): void {
    test('returns the tenant to its starting rows and clears the manifest', function (): void {
        $tenant = tgfDemoTenant();
        $before = Setting::query()->where('tenant_id', $tenant->id)->where('group', '!=', 'tgf-demo')->count();

        Artisan::call('tgf:demo-seed', ['--confirm' => true]);
        expect(tgfFakeRows($tenant))->toBe(1);
        $code = Artisan::call('tgf:demo-remove', ['--confirm' => true]);

        expect($code)->toBe(0)
            ->and(tgfFakeRows($tenant))->toBe(0)
            ->and(Setting::query()->where('tenant_id', $tenant->id)->where('group', '!=', 'tgf-demo')->count())->toBe($before)
            ->and(app(DemoManifest::class)->startedAt($tenant))->toBeNull();
    });

    test('deletes nothing when a finance row older than the seed exists', function (): void {
        $tenant = tgfDemoTenant();
        FinContact::query()->create(['tenant_id' => $tenant->id, 'name' => 'Real Customer', 'email' => 'real@example.com', 'is_customer' => true, 'is_active' => true]);
        $this->travel(1)->minutes();

        Artisan::call('tgf:demo-seed', ['--confirm' => true]);
        $code = Artisan::call('tgf:demo-remove', ['--confirm' => true]);

        expect($code)->not->toBe(0)
            ->and(tgfFakeRows($tenant))->toBe(1)
            ->and(FinContact::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
            ->and(app(DemoManifest::class)->startedAt($tenant))->not->toBeNull();
    });

    test('removing twice is a harmless no-op', function (): void {
        $tenant = tgfDemoTenant();
        Artisan::call('tgf:demo-seed', ['--confirm' => true]);
        Artisan::call('tgf:demo-remove', ['--confirm' => true]);

        $code = Artisan::call('tgf:demo-remove', ['--confirm' => true]);

        expect($code)->toBe(0)->and(tgfFakeRows($tenant))->toBe(0);
    });

    test('refuses on production without --confirm', function (): void {
        $tenant = tgfDemoTenant();
        Artisan::call('tgf:demo-seed', ['--confirm' => true]);
        app()->detectEnvironment(fn (): string => 'production');

        $code = Artisan::call('tgf:demo-remove');
        app()->detectEnvironment(fn (): string => 'testing');

        expect($code)->not->toBe(0)->and(tgfFakeRows($tenant))->toBe(1);
    });
});
