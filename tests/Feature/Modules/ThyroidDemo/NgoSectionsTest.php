<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoSections;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;

function tgfNgoTenant(): Tenant
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user, ['slug' => 'thyroid-ghana-foundation']);
    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);
    app(TenantDatabaseManager::class)->configure($tenant);
    app()->forgetInstance(DemoSections::class);

    $modules = ['finance' => 'Finance', 'memberships' => 'Memberships', 'donations' => 'Donations', 'grants' => 'Grants', 'volunteers' => 'Volunteers', 'beneficiaries' => 'Beneficiaries', 'governance' => 'Governance', 'programme-results' => 'ProgrammeResults'];
    foreach (array_keys($modules) as $slug) {
        TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-'.$slug, 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
        app(ModuleManager::class)->enableForTenant($slug, $tenant);
    }
    foreach ($modules as $directory) {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path("Modules/{$directory}/Database/Migrations"), '--realpath' => true, '--force' => true]);
    }

    return $tenant;
}

function tgfAllRowCounts(Tenant $tenant): array
{
    $connection = DB::connection('tenant');
    $counts = [];
    foreach ($connection->getSchemaBuilder()->getTableListing() as $table) {
        $name = str_contains($table, '.') ? explode('.', $table)[1] : $table;
        if (! preg_match('/^(fin|mem|don|grt|vol|ben|gov|pr)_/', $name) || in_array($name, ['fin_accounts', 'fin_settings', 'fin_sequences', 'mem_types'], true)) {
            continue;
        }
        $counts[$name] = $connection->getSchemaBuilder()->hasColumn($name, 'tenant_id')
            ? $connection->table($name)->where('tenant_id', $tenant->id)->count()
            : $connection->table($name)->count();
    }
    ksort($counts);

    return $counts;
}

describe('Demo data across all eight modules', function (): void {
    test('every module gets labelled demo records', function (): void {
        $tenant = tgfNgoTenant();

        $code = Artisan::call('tgf:demo-seed', ['--confirm' => true]);

        expect($code)->toBe(0);
        $connection = DB::connection('tenant');
        $labelled = [
            'don_donors' => 'name', 'don_campaigns' => 'name', 'grt_funders' => 'name', 'grt_grants' => 'title', 'vol_volunteers' => 'name',
            'vol_opportunities' => 'title', 'ben_beneficiaries' => 'full_name', 'gov_members' => 'name', 'gov_meetings' => 'title', 'pr_indicators' => 'name',
            'fin_contacts' => 'name', 'mem_members' => 'name',
        ];
        foreach ($labelled as $table => $column) {
            $names = $connection->table($table)->where('tenant_id', $tenant->id)->pluck($column);
            expect($names->count())->toBeGreaterThan(0, "{$table} has no rows")
                ->and($names->reject(fn (string $n): bool => str_starts_with($n, 'Demo: '))->all())->toBe([], "{$table} has an unlabelled row");
        }
        foreach (['don_donations', 'don_pledges', 'grt_instalments', 'vol_hours', 'ben_cases', 'ben_services', 'gov_resolutions', 'pr_readings'] as $table) {
            expect($connection->table($table)->count())->toBeGreaterThan(0, "{$table} is empty");
        }
        expect($connection->table('don_pledges')->where('tenant_id', $tenant->id)->where(fn ($q) => $q->whereNotNull('authorization_code')->orWhereNotNull('consent_at')->orWhere('auto_charge', true))->count())->toBe(0);
    });

    test('removal returns every module table to its starting row count', function (): void {
        $tenant = tgfNgoTenant();
        $before = tgfAllRowCounts($tenant);

        Artisan::call('tgf:demo-seed', ['--confirm' => true]);
        expect(tgfAllRowCounts($tenant))->not->toBe($before);
        $code = Artisan::call('tgf:demo-remove', ['--confirm' => true]);

        expect($code)->toBe(0)->and(tgfAllRowCounts($tenant))->toBe($before);
    });

    test('the scheduled jobs send no mail, no notification and make no charge against demo data', function (): void {
        tgfNgoTenant();
        Artisan::call('tgf:demo-seed', ['--confirm' => true]);
        Mail::fake();
        Notification::fake();
        Http::fake();

        foreach (['finance:run-recurring', 'finance:alert-overdue', 'memberships:sync', 'donations:charge-pledges'] as $command) {
            Artisan::call($command);
        }

        Mail::assertNothingSent();
        Notification::assertNothingSent();
        Http::assertNothingSent();
    });

    test('seeding and removing twice leaves the same state', function (): void {
        $tenant = tgfNgoTenant();
        $before = tgfAllRowCounts($tenant);

        Artisan::call('tgf:demo-seed', ['--confirm' => true]);
        $seeded = tgfAllRowCounts($tenant);
        Artisan::call('tgf:demo-seed', ['--confirm' => true]);
        expect(tgfAllRowCounts($tenant))->toBe($seeded);

        Artisan::call('tgf:demo-remove', ['--confirm' => true]);
        Artisan::call('tgf:demo-remove', ['--confirm' => true]);
        expect(tgfAllRowCounts($tenant))->toBe($before);
    });
});
