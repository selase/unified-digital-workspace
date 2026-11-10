<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\Volunteers\Database\Seeders\VolunteersDemoSeeder;
use App\Modules\Volunteers\Models\VolHour;
use App\Modules\Volunteers\Models\VolOpportunity;
use App\Modules\Volunteers\Models\VolVolunteer;
use App\Modules\Volunteers\Services\VolunteerService;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createVolunteersContext(bool $enableModule = true): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [...config('modules.finance.permissions'), ...config('modules.volunteers.permissions')];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['category' => 'volunteers', 'uuid' => (string) Str::uuid()]);
    }
    $user->givePermissionTo($permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    foreach ($enableModule ? ['finance', 'volunteers'] : ['finance'] as $slug) {
        TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-'.$slug, 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
        app(ModuleManager::class)->enableForTenant($slug, $tenant);
    }
    foreach (['Finance', 'Volunteers'] as $module) {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path("Modules/{$module}/Database/Migrations"), '--realpath' => true, '--force' => true]);
    }

    return [$user, $tenant];
}

function activeVolunteer(Tenant $tenant, string $name = 'Ama', string $email = 'ama@example.test'): VolVolunteer
{
    return app(VolunteerService::class)->register($tenant->id, $name, ['email' => $email], true);
}

describe('Volunteer rules', function (): void {
    test('staff-added volunteers are active, public sign-ups wait, and an email is only registered once', function (): void {
        [, $tenant] = createVolunteersContext();
        $service = app(VolunteerService::class);

        $staff = $service->register($tenant->id, 'Staff Added', ['email' => 'A@Example.test'], true);
        $public = $service->register($tenant->id, 'Public', ['email' => 'p@example.test'], false);

        expect($staff->status)->toBe('active')->and($staff->email)->toBe('a@example.test')->and($staff->joined_on)->not->toBeNull()
            ->and($public->status)->toBe('pending')->and($public->joined_on)->toBeNull()
            ->and(fn () => $service->register($tenant->id, 'Dup', ['email' => 'a@example.test'], true))->toThrow(DomainException::class, 'already registered');

        $service->setStatus($public, 'active');
        expect($public->fresh()->status)->toBe('active')->and($public->fresh()->joined_on)->not->toBeNull()
            ->and(fn () => $service->setStatus($public, 'pending'))->toThrow(DomainException::class);
    });

    test('hours are validated, capped at 24 a day, and only count once approved', function (): void {
        [, $tenant] = createVolunteersContext();
        $service = app(VolunteerService::class);
        $volunteer = activeVolunteer($tenant);
        $pending = $service->register($tenant->id, 'Waiting', ['email' => 'w@example.test'], false);

        expect(fn () => $service->logHours($pending, today(), 2))->toThrow(DomainException::class, 'active volunteer')
            ->and(fn () => $service->logHours($volunteer, today(), 0))->toThrow(DomainException::class)
            ->and(fn () => $service->logHours($volunteer, today(), 25))->toThrow(DomainException::class)
            ->and(fn () => $service->logHours($volunteer, today()->addDay(), 2))->toThrow(DomainException::class, 'future');

        $first = $service->logHours($volunteer, today(), 20, 'Build');
        expect(fn () => $service->logHours($volunteer, today(), 5))->toThrow(DomainException::class, '24 hours')
            ->and($first->minutes)->toBe(1200)->and($first->status)->toBe('pending');

        $summary = fn () => $service->summary($tenant->id, today()->startOfYear(), today());
        expect($summary()['total_minutes'])->toBe(0);

        $service->review($first, true, null);
        expect($summary()['total_minutes'])->toBe(1200)->and($summary()['top'][0]['volunteer']->id)->toBe($volunteer->id)
            ->and(fn () => $service->review($first->fresh(), false, null))->toThrow(DomainException::class);

        $rejected = $service->review($service->logHours($volunteer, today()->subDay(), 3), false, null);
        expect($rejected->status)->toBe('rejected')->and($summary()['total_minutes'])->toBe(1200);
    });

    test('sign-ups respect places, closed opportunities, duplicates and unapproved volunteers', function (): void {
        [, $tenant] = createVolunteersContext();
        $service = app(VolunteerService::class);
        $opportunity = VolOpportunity::query()->create(['tenant_id' => $tenant->id, 'title' => 'Clinic', 'starts_on' => today()->addDay(), 'spots' => 2, 'status' => 'open']);
        $one = activeVolunteer($tenant, 'One', 'one@example.test');
        $two = activeVolunteer($tenant, 'Two', 'two@example.test');
        $three = activeVolunteer($tenant, 'Three', 'three@example.test');

        $service->signUp($opportunity, $one);
        expect(fn () => $service->signUp($opportunity, $one))->toThrow(DomainException::class, 'already signed up');
        $service->signUp($opportunity, $two);
        expect(fn () => $service->signUp($opportunity, $three))->toThrow(DomainException::class, 'places are taken')
            ->and($opportunity->fresh()->isFull())->toBeTrue();

        $unlimited = VolOpportunity::query()->create(['tenant_id' => $tenant->id, 'title' => 'Open day', 'starts_on' => today(), 'spots' => 0, 'status' => 'open']);
        foreach ([$one, $two, $three] as $volunteer) {
            $service->signUp($unlimited, $volunteer);
        }
        expect($unlimited->volunteers()->count())->toBe(3);

        $unlimited->update(['status' => 'closed']);
        expect(fn () => $service->signUp($unlimited->fresh(), activeVolunteer($tenant, 'Four', 'four@example.test')))->toThrow(DomainException::class, 'closed');

        $waiting = $service->register($tenant->id, 'Waiting', ['email' => 'wait@example.test'], false);
        expect(fn () => $service->signUp(VolOpportunity::query()->create(['tenant_id' => $tenant->id, 'title' => 'X', 'starts_on' => today(), 'spots' => 0, 'status' => 'open']), $waiting))->toThrow(DomainException::class, 'active volunteers');
    });
});

describe('Volunteer screens', function (): void {
    test('staff add a volunteer, log and approve hours, create an opportunity and sign someone up', function (): void {
        [$user, $tenant] = createVolunteersContext();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('volunteers.index'))->assertOk()->assertSee('Active volunteers');
        $this->post(route('volunteers.volunteers.store'), ['name' => 'Kofi', 'email' => 'kofi@example.test', 'skills' => 'Driving'])->assertRedirect();
        $volunteer = VolVolunteer::query()->firstOrFail();
        $this->get(route('volunteers.volunteers.show', $volunteer->uuid))->assertOk()->assertSee('Kofi');

        $this->post(route('volunteers.opportunities.store'), ['title' => 'Clinic day', 'starts_on' => today()->addDays(3)->toDateString(), 'spots' => 3])->assertSessionHas('success');
        $opportunity = VolOpportunity::query()->firstOrFail();
        $this->post(route('volunteers.volunteers.signup', $volunteer->uuid), ['opportunity_id' => $opportunity->id])->assertSessionHas('success');
        $this->get(route('volunteers.opportunities.index'))->assertOk()->assertSee('Kofi');

        $this->post(route('volunteers.hours.store'), ['volunteer_id' => $volunteer->id, 'worked_on' => today()->toDateString(), 'hours' => '3.5', 'activity' => 'Set-up'])->assertSessionHas('success');
        $hour = VolHour::query()->firstOrFail();
        expect($hour->minutes)->toBe(210);
        $this->get(route('volunteers.hours.index'))->assertOk()->assertSee('Set-up');
        $this->post(route('volunteers.hours.review', $hour->uuid), ['decision' => 'approve'])->assertSessionHas('success');
        expect($hour->fresh()->status)->toBe('approved');

        $this->post(route('volunteers.opportunities.toggle', $opportunity->uuid))->assertRedirect();
        expect($opportunity->fresh()->status)->toBe('closed');
        $this->post(route('volunteers.volunteers.status', $volunteer->uuid), ['status' => 'inactive'])->assertSessionHas('success');
        $this->get(route('volunteers.volunteers.index', ['status' => 'inactive', 'search' => 'Kofi']))->assertOk()->assertSee('Kofi');
    });

    test('validation and permissions are enforced', function (): void {
        [$user, $tenant] = createVolunteersContext();
        $volunteer = activeVolunteer($tenant);
        $hour = app(VolunteerService::class)->logHours($volunteer, today(), 2);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->post(route('volunteers.hours.store'), ['hours' => '30'])->assertSessionHasErrors(['volunteer_id', 'hours']);
        $this->post(route('volunteers.volunteers.store'), [])->assertSessionHasErrors('name');

        $user->revokePermissionTo('volunteers.hours.approve');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->post(route('volunteers.hours.review', $hour->uuid), ['decision' => 'approve'])->assertForbidden();

        $user->revokePermissionTo(['volunteers.manage']);
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->post(route('volunteers.volunteers.store'), ['name' => 'X'])->assertForbidden();
        $this->get(route('volunteers.index'))->assertOk();

        $user->revokePermissionTo('volunteers.view');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->get(route('volunteers.index'))->assertForbidden();
    });
});

describe('Volunteer certificate and roster', function (): void {
    test('a certificate needs approved hours, and the roster lists who signed up', function (): void {
        [$user, $tenant] = createVolunteersContext();
        $service = app(VolunteerService::class);
        $volunteer = activeVolunteer($tenant, 'Efua Mensah', 'efua@example.test');
        $opportunity = VolOpportunity::query()->create(['tenant_id' => $tenant->id, 'title' => 'Clinic day', 'starts_on' => today()->addDay(), 'spots' => 0, 'status' => 'open']);
        $service->signUp($opportunity, $volunteer);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('volunteers.volunteers.certificate', $volunteer->uuid))->assertRedirect()->assertSessionHas('error');
        $service->review($service->logHours($volunteer, today(), 4, 'Shift'), true, null);
        $service->logHours($volunteer, today()->subDay(), 9, 'Waiting');

        $pdf = $this->get(route('volunteers.volunteers.certificate', $volunteer->uuid));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect(mb_substr($pdf->getContent(), 0, 4))->toBe('%PDF');
        $this->get(route('volunteers.volunteers.show', $volunteer->uuid))->assertSee('Certificate');

        $csv = $this->get(route('volunteers.opportunities.roster', $opportunity->uuid));
        $csv->assertOk()->assertDownload();
        expect($csv->streamedContent())->toContain('Roster: Clinic day', 'Efua Mensah');
        $this->get(route('volunteers.opportunities.roster', [$opportunity->uuid, 'format' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    });
});

describe('Public volunteer sign-up', function (): void {
    test('a sign-up is saved as pending, a repeat looks identical, and bots and bad input are rejected', function (): void {
        [, $tenant] = createVolunteersContext();
        $url = route('volunteers.public.store', ['tenant' => $tenant->slug]);

        $this->get(route('volunteers.public.signup', ['tenant' => $tenant->slug]))->assertOk()->assertSee('Offer to volunteer');
        $this->post($url, ['name' => 'Efua', 'email' => 'efua@example.test', 'skills' => 'Cooking'])->assertOk()->assertSee('Thank you');
        $this->post($url, ['name' => 'Efua Again', 'email' => 'efua@example.test'])->assertOk()->assertSee('Thank you');

        expect(VolVolunteer::query()->count())->toBe(1)->and(VolVolunteer::query()->first()->status)->toBe('pending');

        $this->post($url, ['name' => 'Bot', 'email' => 'bot@example.test', 'website' => 'http://spam'])->assertSessionHasErrors('website');
        $this->post($url, ['name' => '', 'email' => 'not-an-email'])->assertSessionHasErrors(['name', 'email']);
        expect(VolVolunteer::query()->count())->toBe(1);
    });

    test('the page does not exist for unknown organisations or ones without the module', function (): void {
        [, $tenant] = createVolunteersContext(false);

        $this->get(route('volunteers.public.signup', ['tenant' => $tenant->slug]))->assertNotFound();
        $this->get(route('volunteers.public.signup', ['tenant' => 'nobody']))->assertNotFound();
    });
});

describe('Volunteers demo seeder', function (): void {
    test('creates a roster with hours, once, and refuses production', function (): void {
        [, $tenant] = createVolunteersContext();
        config(['app.demo_tenant' => $tenant->slug]);

        $this->seed(VolunteersDemoSeeder::class);

        expect(VolVolunteer::query()->count())->toBe(10)->and(VolVolunteer::query()->where('status', 'pending')->count())->toBe(2)
            ->and(VolOpportunity::query()->count())->toBe(3)->and(VolHour::query()->where('status', 'approved')->count())->toBe(9)
            ->and(VolHour::query()->where('status', 'pending')->count())->toBe(3);

        $count = VolHour::query()->count();
        $this->seed(VolunteersDemoSeeder::class);
        expect(VolHour::query()->count())->toBe($count);

        VolHour::query()->delete();
        VolOpportunity::query()->delete();
        VolVolunteer::query()->forceDelete();
        app()->detectEnvironment(fn (): string => 'production');
        (new VolunteersDemoSeeder)->run();
        app()->detectEnvironment(fn (): string => 'testing');
        expect(VolVolunteer::query()->count())->toBe(0);
    });
});
