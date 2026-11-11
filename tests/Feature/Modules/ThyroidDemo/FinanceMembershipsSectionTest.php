<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Memberships\Models\MemMember;
use App\Modules\Memberships\Models\MemType;
use App\Modules\SiteThyroidGhanaFoundation\Demo\Sections\FinanceSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\Sections\MembershipsSection;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;

function tgfSectionTenant(): Tenant
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);
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
    foreach ([['Student and Patient Member', 10_000], ['Individual Member', 20_000], ['Corporate Partner', 200_000]] as [$name, $fee]) {
        MemType::query()->create(['tenant_id' => $tenant->id, 'name' => $name, 'fee' => $fee, 'period_months' => 12]);
    }

    return $tenant;
}

function tgfRowCounts(Tenant $tenant): array
{
    $connection = DB::connection('tenant');
    $counts = [];
    foreach ($connection->getSchemaBuilder()->getTableListing() as $table) {
        $name = str_contains($table, '.') ? explode('.', $table)[1] : $table;
        if (! preg_match('/^(fin|mem)_/', $name) || in_array($name, ['fin_accounts', 'fin_settings', 'fin_sequences', 'mem_types'], true)) {
            continue;
        }
        $counts[$name] = $connection->getSchemaBuilder()->hasColumn($name, 'tenant_id')
            ? $connection->table($name)->where('tenant_id', $tenant->id)->count()
            : $connection->table($name)->count();
    }
    ksort($counts);

    return $counts;
}

describe('Finance demo section', function (): void {
    test('seeds only Demo: labelled contacts and a balanced ledger, then removes all of it', function (): void {
        $tenant = tgfSectionTenant();
        $since = CarbonImmutable::now()->startOfSecond();
        $before = tgfRowCounts($tenant);
        $section = new FinanceSection;

        $section->seed($tenant);

        $contacts = FinContact::query()->where('tenant_id', $tenant->id)->pluck('name');
        $lines = DB::connection('tenant')->table('fin_journal_lines')->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')->first();
        expect($contacts->count())->toBeGreaterThan(3)
            ->and($contacts->reject(fn (string $name): bool => str_starts_with($name, 'Demo: ')))->toBeEmpty()
            ->and((int) $lines->d)->toBe((int) $lines->c)->and((int) $lines->d)->toBeGreaterThan(0)
            ->and(FinInvoice::query()->where('tenant_id', $tenant->id)->where('currency', 'USD')->count())->toBe(1);

        $removed = $section->remove($tenant, $since);

        expect($removed)->toBeGreaterThan(0)->and(tgfRowCounts($tenant))->toBe($before);
    });

    test('keeps the chart of accounts and the finance settings', function (): void {
        $tenant = tgfSectionTenant();
        $since = CarbonImmutable::now()->startOfSecond();
        $accounts = DB::connection('tenant')->table('fin_accounts')->where('tenant_id', $tenant->id)->count();

        $section = new FinanceSection;
        $section->seed($tenant);
        $section->remove($tenant, $since);

        expect(DB::connection('tenant')->table('fin_accounts')->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual($accounts);
    });

    test('sends no mail or notification while seeding', function (): void {
        Mail::fake();
        Notification::fake();
        $tenant = tgfSectionTenant();

        (new FinanceSection)->seed($tenant);

        Mail::assertNothingSent();
        Notification::assertNothingSent();
    });
});

describe('Memberships demo section', function (): void {
    test('seeds labelled members across the tiers with one waiting to pay and one due to renew, then removes them', function (): void {
        $tenant = tgfSectionTenant();
        $since = CarbonImmutable::now()->startOfSecond();
        $before = tgfRowCounts($tenant);
        $finance = new FinanceSection;
        $memberships = new MembershipsSection;

        $finance->seed($tenant);
        $memberships->seed($tenant);

        $members = MemMember::query()->where('tenant_id', $tenant->id)->get();
        expect($members->count())->toBeGreaterThanOrEqual(6)
            ->and($members->reject(fn (MemMember $m): bool => str_starts_with($m->name, 'Demo: ')))->toBeEmpty()
            ->and($members->where('status', 'pending')->count())->toBe(1)
            ->and($members->where('status', 'active')->filter(fn (MemMember $m): bool => $m->expires_on !== null && $m->expires_on->lte(today()->addDays(60)))->count())->toBe(1)
            ->and($members->pluck('type_id')->unique()->count())->toBe(3);

        $memberships->remove($tenant, $since);
        $finance->remove($tenant, $since);

        expect(tgfRowCounts($tenant))->toBe($before)
            ->and(MemType::query()->where('tenant_id', $tenant->id)->count())->toBe(3);
    });
});
