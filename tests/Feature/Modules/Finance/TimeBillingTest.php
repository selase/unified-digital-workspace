<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Services\InvoiceService;
use App\Modules\Finance\Services\TimeBillingService;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\TimeEntry;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant, 2: Project, 3: FinContact}
 */
function createTimeBillingContext(bool $withProjects = true): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = config('modules.finance.permissions');
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['category' => 'finance', 'uuid' => (string) Str::uuid()]);
    }
    $user->givePermissionTo($permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    foreach ($withProjects ? ['finance', 'project-management'] : ['finance'] as $slug) {
        TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-'.$slug, 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
        app(ModuleManager::class)->enableForTenant($slug, $tenant);
    }
    foreach (['Finance', 'ProjectManagement'] as $module) {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path("Modules/{$module}/Database/Migrations"), '--realpath' => true, '--force' => true]);
    }

    $project = Project::query()->create(['tenant_id' => $tenant->id, 'name' => 'Website rebuild', 'slug' => 'website-rebuild', 'status' => 'in-progress', 'priority' => 'medium']);
    $contact = FinContact::query()->create(['tenant_id' => $tenant->id, 'name' => 'Acme Ltd', 'email' => 'acme@example.test', 'is_customer' => true, 'is_active' => true]);

    return [$user, $tenant, $project, $contact];
}

function logTime(Tenant $tenant, Project $project, string $task, int $minutes, string $date = '2026-11-03'): TimeEntry
{
    $model = Task::query()->where('project_id', $project->id)->where('title', $task)->first()
        ?? Task::query()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => $task, 'status' => 'todo', 'priority' => 'medium']);

    return TimeEntry::query()->create(['tenant_id' => $tenant->id, 'task_id' => $model->id, 'user_id' => (string) Str::uuid(), 'entry_date' => $date, 'minutes' => $minutes]);
}

describe('Billing project time', function (): void {
    test('a draft invoice has a line per task, entries are billed once, and voiding frees them', function (): void {
        [, $tenant, $project, $contact] = createTimeBillingContext();
        $service = app(TimeBillingService::class);
        logTime($tenant, $project, 'Design', 90);
        logTime($tenant, $project, 'Design', 30, '2026-11-04');
        logTime($tenant, $project, 'Build', 120);
        logTime($tenant, $project, 'Outside range', 600, '2026-10-01');
        $from = CarbonImmutable::parse('2026-11-01');
        $to = CarbonImmutable::parse('2026-11-30');

        $rows = $service->unbilled($tenant->id, $project, $from, $to);
        expect($rows)->toHaveCount(2)->and($rows->sum('minutes'))->toBe(240);

        $invoice = $service->createDraftInvoice($tenant->id, $contact, $project, $from, $to, 15_000);
        expect($invoice->status)->toBe(FinInvoice::STATUS_DRAFT)->and($invoice->lines)->toHaveCount(2)
            ->and($invoice->total)->toBe(60_000)
            ->and($invoice->lines->firstWhere(fn ($line): bool => str_contains($line->description, 'Design'))->quantity_thousandths)->toBe(2000);

        expect($service->unbilled($tenant->id, $project, $from, $to))->toHaveCount(0)
            ->and(fn () => $service->createDraftInvoice($tenant->id, $contact, $project, $from, $to, 15_000))->toThrow(DomainException::class, 'no unbilled time')
            ->and(fn () => $service->createDraftInvoice($tenant->id, $contact, $project, $from, $to, 0))->toThrow(DomainException::class, 'hourly rate');

        $sent = app(InvoiceService::class)->send($invoice);
        app(InvoiceService::class)->void($sent);
        expect($service->unbilled($tenant->id, $project, $from, $to)->sum('minutes'))->toBe(240);
    });

    test('the screen shows unbilled hours, creates the draft, and is hidden without Project Management or permission', function (): void {
        [$user, $tenant, $project, $contact] = createTimeBillingContext();
        logTime($tenant, $project, 'Design', 60);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('finance.time-billing.index', ['project' => $project->uuid, 'from' => '2026-11-01', 'to' => '2026-11-30']))->assertOk()->assertSee('Design')->assertSee('1.00');
        $this->get(route('finance.invoices.index'))->assertOk()->assertSee('Bill project time');

        $this->post(route('finance.time-billing.store'), [])->assertSessionHasErrors(['project', 'contact_id', 'hourly_rate']);
        $this->post(route('finance.time-billing.store'), ['project' => $project->uuid, 'contact_id' => $contact->id, 'from' => '2026-11-01', 'to' => '2026-11-30', 'hourly_rate' => '200'])->assertRedirect();
        expect(FinInvoice::query()->firstOrFail()->total)->toBe(20_000);
        $this->post(route('finance.time-billing.store'), ['project' => $project->uuid, 'contact_id' => $contact->id, 'from' => '2026-11-01', 'to' => '2026-11-30', 'hourly_rate' => '200'])->assertSessionHas('error');

        $user->revokePermissionTo('finance.invoices.manage');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->post(route('finance.time-billing.store'), ['project' => $project->uuid, 'contact_id' => $contact->id, 'from' => '2026-11-01', 'to' => '2026-11-30', 'hourly_rate' => '200'])->assertForbidden();
    });

    test('the page is not available when the organisation does not use Project Management', function (): void {
        [$user, $tenant] = createTimeBillingContext(false);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('finance.time-billing.index'))->assertNotFound();
        $this->get(route('finance.invoices.index'))->assertOk()->assertDontSee('Bill project time');
    });
});
