<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\Governance\Database\Seeders\GovernanceDemoSeeder;
use App\Modules\Governance\Models\GovAction;
use App\Modules\Governance\Models\GovMeeting;
use App\Modules\Governance\Models\GovMember;
use App\Modules\Governance\Models\GovResolution;
use App\Modules\Governance\Services\GovernanceService;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<string>|null  $only
 * @return array{0: User, 1: Tenant}
 */
function createGovernanceContext(?array $only = null): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = config('modules.governance.permissions');
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['category' => 'governance', 'uuid' => (string) Str::uuid()]);
    }
    $user->givePermissionTo($only ?? $permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-governance', 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
    app(ModuleManager::class)->enableForTenant('governance', $tenant);
    Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path('Modules/Governance/Database/Migrations'), '--realpath' => true, '--force' => true]);

    return [$user, $tenant];
}

/** @return array{0: GovMeeting, 1: list<GovMember>} */
function heldMeeting(Tenant $tenant, int $present = 3, ?int $quorum = 3): array
{
    $service = app(GovernanceService::class);
    $members = [];
    foreach (range(1, 4) as $i) {
        $members[] = $service->addMember($tenant->id, "Member {$i}", ['position' => $i === 1 ? 'Chair' : 'Member']);
    }
    $meeting = $service->schedule($tenant->id, 'Board meeting', 'Board', CarbonImmutable::now()->subDay(), 'Ho', $quorum, "1. Welcome\n2. Accounts");
    $statuses = [];
    foreach ($members as $index => $member) {
        $statuses[$member->id] = $index < $present ? 'present' : 'apologies';
    }
    $service->recordAttendance($meeting, $statuses);

    return [$meeting, $members];
}

describe('Meeting rules', function (): void {
    test('a meeting needs attendance before it is held, and quorum is worked out from who was present', function (): void {
        [, $tenant] = createGovernanceContext();
        $service = app(GovernanceService::class);
        $member = $service->addMember($tenant->id, 'Ama', []);
        $meeting = $service->schedule($tenant->id, 'Board', 'Board', CarbonImmutable::now()->subDay(), null, 2, null);

        expect($service->quorumMet($meeting))->toBeFalse()
            ->and(fn () => $service->markHeld($meeting))->toThrow(DomainException::class, 'who attended')
            ->and(fn () => $service->recordAttendance($meeting, [999 => 'present']))->toThrow(DomainException::class, 'not a member')
            ->and(fn () => $service->recordAttendance($meeting, [$member->id => 'sleeping']))->toThrow(DomainException::class);

        $service->recordAttendance($meeting, [$member->id => 'present']);
        expect($service->quorumMet($meeting))->toBeFalse();
        $second = $service->addMember($tenant->id, 'Kofi', []);
        $service->recordAttendance($meeting, [$member->id => 'present', $second->id => 'present']);
        expect($service->quorumMet($meeting))->toBeTrue();

        $noRule = $service->schedule($tenant->id, 'No rule', 'Board', CarbonImmutable::now()->subDay(), null, null, null);
        expect($service->quorumMet($noRule))->toBeNull()
            ->and(fn () => $service->schedule($tenant->id, 'Bad', 'Board', CarbonImmutable::now(), null, 0, null))->toThrow(DomainException::class, 'quorum');

        $future = $service->schedule($tenant->id, 'Future', 'Board', CarbonImmutable::now()->addDays(10), null, null, null);
        $service->recordAttendance($future, [$member->id => 'present']);
        expect(fn () => $service->markHeld($future))->toThrow(DomainException::class, 'future');
    });

    test('minutes need a held meeting, approval locks them, and approval cannot be repeated or backdated', function (): void {
        [, $tenant] = createGovernanceContext();
        $service = app(GovernanceService::class);
        [$meeting] = heldMeeting($tenant);

        expect(fn () => $service->saveMinutes($meeting, 'Too early'))->toThrow(DomainException::class, 'held')
            ->and(fn () => $service->addResolution($meeting, 'Too early', 'passed'))->toThrow(DomainException::class, 'held');

        $service->markHeld($meeting);
        expect($meeting->fresh()->minutes_status)->toBe('draft')->and(fn () => $service->approveMinutes($meeting->fresh(), CarbonImmutable::today()))->toThrow(DomainException::class, 'written');

        $service->saveMinutes($meeting->fresh(), 'The board met and agreed the accounts.');
        expect(fn () => $service->approveMinutes($meeting->fresh(), CarbonImmutable::now()->subDays(5)))->toThrow(DomainException::class, 'before the meeting');

        $service->approveMinutes($meeting->fresh(), CarbonImmutable::today());
        expect($meeting->fresh()->minutes_status)->toBe('approved')
            ->and(fn () => $service->saveMinutes($meeting->fresh(), 'Edited later'))->toThrow(DomainException::class, 'locked')
            ->and(fn () => $service->approveMinutes($meeting->fresh(), CarbonImmutable::today()))->toThrow(DomainException::class);
    });

    test('resolutions are numbered per year without gaps and actions need an owner', function (): void {
        [, $tenant] = createGovernanceContext();
        $service = app(GovernanceService::class);
        [$meeting, $members] = heldMeeting($tenant);
        $service->markHeld($meeting);
        $year = $meeting->meets_at->format('Y');

        expect($service->addResolution($meeting->fresh(), 'Approve accounts', 'passed')->reference)->toBe("R-{$year}-001")
            ->and($service->addResolution($meeting->fresh(), 'Appoint auditor', 'deferred')->reference)->toBe("R-{$year}-002")
            ->and(fn () => $service->addResolution($meeting->fresh(), 'Odd', 'maybe'))->toThrow(DomainException::class);

        expect(fn () => $service->addAction($tenant->id, 'Do a thing', $meeting, null, '  ', null))->toThrow(DomainException::class, 'responsible');
        $action = $service->addAction($tenant->id, 'Send accounts to the auditor', $meeting, $members[1], null, CarbonImmutable::today()->subDay());
        expect($action->owner_name)->toBe('Member 2')->and($action->isOverdue())->toBeTrue();
        $service->setActionStatus($action, true);
        expect($action->fresh()->status)->toBe('done')->and($action->fresh()->completed_on)->not->toBeNull()->and($action->fresh()->isOverdue())->toBeFalse();
        $service->setActionStatus($action->fresh(), false);
        expect($action->fresh()->completed_on)->toBeNull();
    });

    test('members have valid terms, can be made former, and terms ending soon are listed', function (): void {
        [, $tenant] = createGovernanceContext();
        $service = app(GovernanceService::class);

        expect(fn () => $service->addMember($tenant->id, 'Bad', ['term_starts_on' => '2026-05-01', 'term_ends_on' => '2026-01-01']))->toThrow(DomainException::class, 'term');
        $soon = $service->addMember($tenant->id, 'Soon', ['term_ends_on' => today()->addDays(30)->toDateString()]);
        $service->addMember($tenant->id, 'Later', ['term_ends_on' => today()->addYear()->toDateString()]);
        expect($service->termsEnding($tenant->id)->pluck('name')->all())->toBe(['Soon']);
        $service->setMemberStatus($soon, 'former');
        expect($service->termsEnding($tenant->id))->toHaveCount(0)->and(fn () => $service->setMemberStatus($soon, 'retired'))->toThrow(DomainException::class);
    });
});

describe('Governance screens', function (): void {
    test('staff run a meeting from schedule to approved minutes and download the pack', function (): void {
        [$user, $tenant] = createGovernanceContext();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('governance.index'))->assertOk()->assertSee('Board & Governance');
        $this->post(route('governance.members.store'), ['name' => 'Dr. Mensah', 'position' => 'Chair', 'term_ends_on' => today()->addDays(20)->toDateString()])->assertSessionHas('success');
        $member = GovMember::query()->firstOrFail();
        $this->get(route('governance.members.index'))->assertOk()->assertSee('Dr. Mensah');

        $this->post(route('governance.meetings.store'), ['title' => 'Q4 board', 'body' => 'Board', 'meets_at' => now()->subHour()->format('Y-m-d\TH:i'), 'quorum' => 1, 'agenda' => '1. Accounts'])->assertRedirect();
        $meeting = GovMeeting::query()->firstOrFail();
        $this->get(route('governance.meetings.show', $meeting->uuid))->assertOk()->assertSee('Q4 board')->assertSee('Save agenda');

        $this->post(route('governance.meetings.hold', $meeting->uuid))->assertSessionHas('error');
        $this->post(route('governance.meetings.attendance', $meeting->uuid), ['attendance' => [$member->id => 'present']])->assertSessionHas('success');
        $this->post(route('governance.meetings.hold', $meeting->uuid))->assertSessionHas('success');
        $this->post(route('governance.meetings.minutes', $meeting->uuid), ['minutes' => 'The board agreed.'])->assertSessionHas('success');
        $this->post(route('governance.meetings.resolutions.store', $meeting->uuid), ['text' => 'Approve accounts', 'outcome' => 'passed'])->assertSessionHas('success');
        $this->post(route('governance.actions.store'), ['description' => 'File returns', 'member_id' => $member->id, 'due_on' => today()->addWeek()->toDateString(), 'meeting_id' => $meeting->id])->assertSessionHas('success');
        $this->post(route('governance.meetings.approve-minutes', $meeting->uuid), ['date' => today()->toDateString()])->assertSessionHas('success');

        expect($meeting->fresh()->minutes_status)->toBe('approved')->and(GovResolution::query()->count())->toBe(1);
        $this->post(route('governance.meetings.minutes', $meeting->uuid), ['minutes' => 'Sneaky edit'])->assertSessionHas('error');
        $this->get(route('governance.actions.index'))->assertOk()->assertSee('File returns');
        $this->post(route('governance.actions.toggle', GovAction::query()->firstOrFail()->uuid))->assertSessionHas('success');

        $pdf = $this->get(route('governance.meetings.pack', $meeting->uuid));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect(mb_substr($pdf->getContent(), 0, 4))->toBe('%PDF');
        $this->get(route('governance.meetings.index'))->assertOk()->assertSee('Q4 board');
    });

    test('validation, approval permission and organisation boundaries are enforced', function (): void {
        [$user, $tenant] = createGovernanceContext(['governance.view', 'governance.manage']);
        [$meeting] = heldMeeting($tenant);
        $service = app(GovernanceService::class);
        $service->markHeld($meeting);
        $service->saveMinutes($meeting->fresh(), 'Minutes.');
        $foreign = GovMeeting::query()->create(['tenant_id' => (string) Tenant::factory()->create()->id, 'title' => 'Other org', 'meets_at' => now()]);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->post(route('governance.members.store'), [])->assertSessionHasErrors('name');
        $this->post(route('governance.meetings.store'), [])->assertSessionHasErrors(['title', 'meets_at']);
        $this->post(route('governance.actions.store'), [])->assertSessionHasErrors('description');
        $this->get(route('governance.meetings.show', $foreign->uuid))->assertNotFound();

        $this->post(route('governance.meetings.approve-minutes', $meeting->uuid))->assertForbidden();
        expect($meeting->fresh()->minutes_status)->toBe('draft');

        $user->revokePermissionTo('governance.manage');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->post(route('governance.meetings.store'), ['title' => 'X', 'meets_at' => now()->format('Y-m-d\TH:i')])->assertForbidden();
        $this->get(route('governance.index'))->assertOk();

        $user->revokePermissionTo('governance.view');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->get(route('governance.index'))->assertForbidden();
    });

    test('the demo seeder creates a board with meetings once and refuses production', function (): void {
        [, $tenant] = createGovernanceContext();
        config(['app.demo_tenant' => $tenant->slug]);

        $this->seed(GovernanceDemoSeeder::class);
        expect(GovMember::query()->count())->toBe(5)->and(GovMeeting::query()->count())->toBe(3)->and(GovMeeting::query()->where('minutes_status', 'approved')->count())->toBe(1)
            ->and(GovAction::query()->count())->toBe(2)->and(GovResolution::query()->count())->toBe(2);
        $this->seed(GovernanceDemoSeeder::class);
        expect(GovMember::query()->count())->toBe(5);

        GovAction::query()->delete();
        GovResolution::query()->delete();
        GovMeeting::query()->delete();
        GovMember::query()->delete();
        app()->detectEnvironment(fn (): string => 'production');
        (new GovernanceDemoSeeder)->run();
        app()->detectEnvironment(fn (): string => 'testing');
        expect(GovMember::query()->count())->toBe(0);
    });
});
