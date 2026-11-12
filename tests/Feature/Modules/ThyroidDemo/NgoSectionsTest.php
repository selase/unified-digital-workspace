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

    $modules = ['finance' => 'Finance', 'memberships' => 'Memberships', 'donations' => 'Donations', 'grants' => 'Grants', 'volunteers' => 'Volunteers', 'beneficiaries' => 'Beneficiaries', 'governance' => 'Governance', 'results-framework' => 'ResultsFramework'];
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
        if (! preg_match('/^(fin|mem|don|grt|vol|ben|gov|rf)_/', $name) || in_array($name, ['fin_accounts', 'fin_settings', 'fin_sequences', 'mem_types'], true)) {
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
            'vol_opportunities' => 'title', 'ben_beneficiaries' => 'full_name', 'gov_members' => 'name', 'gov_meetings' => 'title', 'rf_indicators' => 'name',
            'fin_contacts' => 'name', 'mem_members' => 'name',
        ];
        foreach ($labelled as $table => $column) {
            $names = $connection->table($table)->where('tenant_id', $tenant->id)->pluck($column);
            expect($names->count())->toBeGreaterThan(0, "{$table} has no rows")
                ->and($names->reject(fn (string $n): bool => str_starts_with($n, 'Demo: '))->all())->toBe([], "{$table} has an unlabelled row");
        }
        foreach (['don_donations', 'don_pledges', 'grt_instalments', 'vol_hours', 'ben_cases', 'ben_services', 'gov_resolutions', 'rf_readings'] as $table) {
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

    test('removal still works after the nightly jobs have run', function (): void {
        $tenant = tgfNgoTenant();
        $before = tgfAllRowCounts($tenant);
        Artisan::call('tgf:demo-seed', ['--confirm' => true]);

        foreach (['finance:run-recurring', 'finance:alert-overdue', 'memberships:sync', 'donations:charge-pledges'] as $command) {
            Artisan::call($command);
        }
        $code = Artisan::call('tgf:demo-remove', ['--confirm' => true]);

        expect($code)->toBe(0)->and(tgfAllRowCounts($tenant))->toBe($before);
    });

    test('removal keeps volunteers, beneficiaries and board members registered for real during the demo window', function (): void {
        $tenant = tgfNgoTenant();
        Artisan::call('tgf:demo-seed', ['--confirm' => true]);
        app(App\Modules\Volunteers\Services\VolunteerService::class)->register($tenant->id, 'Real Volunteer', ['email' => 'real.volunteer@example.com'], false);
        app(App\Modules\Beneficiaries\Services\BeneficiaryService::class)->register($tenant->id, 'Real Person', ['gender' => 'female', 'programme' => 'Screening camps'], null);
        app(App\Modules\Governance\Services\GovernanceService::class)->addMember($tenant->id, 'Real Trustee', ['position' => 'Member', 'term_starts_on' => today()->toDateString(), 'term_ends_on' => today()->addYear()->toDateString()]);

        $code = Artisan::call('tgf:demo-remove', ['--confirm' => true]);

        $connection = DB::connection('tenant');
        expect($code)->toBe(0)
            ->and($connection->table('vol_volunteers')->where('tenant_id', $tenant->id)->pluck('name')->all())->toBe(['Real Volunteer'])
            ->and($connection->table('ben_beneficiaries')->where('tenant_id', $tenant->id)->pluck('full_name')->all())->toBe(['Real Person'])
            ->and($connection->table('gov_members')->where('tenant_id', $tenant->id)->pluck('name')->all())->toBe(['Real Trustee'])
            ->and($connection->table('vol_hours')->where('tenant_id', $tenant->id)->count())->toBe(0)
            ->and($connection->table('ben_services')->where('tenant_id', $tenant->id)->count())->toBe(0);
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

test('results cleanup removes only newly seeded labelled programmes with no indicators left', function (): void {
    $tenant = tgfNgoTenant();
    $manifest = app(App\Modules\SiteThyroidGhanaFoundation\Demo\DemoManifest::class);
    $section = new App\Modules\SiteThyroidGhanaFoundation\Demo\Sections\ResultsSection;
    $programmes = app(App\Modules\ResultsFramework\Services\ProgrammeService::class);
    $older = $programmes->create($tenant->id, 'Demo: Earlier programme');
    $older->forceFill(['created_at' => now()->subDay()])->save();
    $since = $manifest->begin($tenant);
    $section->seed($tenant);
    $created = App\Modules\ResultsFramework\Models\ResultProgramme::query()->where('tenant_id', $tenant->id)->where('created_at', '>=', $since)->get();
    expect($created)->not->toBeEmpty()
        ->and($created->every(fn ($programme): bool => str_starts_with($programme->name, 'Demo: ')))->toBeTrue();

    $withIndicator = $created->first();
    $unlabelled = $created->last();
    $unlabelled->update(['name' => 'Keep renamed programme']);
    $notSeeded = $programmes->create($tenant->id, 'Demo: Manually created');
    $real = app(App\Modules\ResultsFramework\Services\IndicatorService::class)->create($tenant->id, 'Keep real indicator', 10, today(), today()->addDay(), ['programme_id' => $withIndicator->id]);
    $section->remove($tenant, $since);
    expect($real->fresh())->not->toBeNull()
        ->and($withIndicator->fresh())->not->toBeNull()
        ->and($unlabelled->fresh())->not->toBeNull()
        ->and($notSeeded->fresh())->not->toBeNull()
        ->and($older->fresh())->not->toBeNull()
        ->and($created->except([$withIndicator->id, $unlabelled->id])->every(fn ($programme): bool => $programme->fresh() === null))->toBeTrue();
});
