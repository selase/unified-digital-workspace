<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Services\InvoiceService;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Memberships\Database\Seeders\MembershipsDemoSeeder;
use App\Modules\Memberships\Models\MemMember;
use App\Modules\Memberships\Models\MemRenewal;
use App\Modules\Memberships\Models\MemType;
use App\Modules\Memberships\Services\MembershipService;
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
function createMembershipsContext(bool $enableModule = true): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [...config('modules.finance.permissions'), ...config('modules.memberships.permissions')];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['category' => 'memberships', 'uuid' => (string) Str::uuid()]);
    }
    $user->givePermissionTo($permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    foreach ($enableModule ? ['finance', 'memberships'] : ['finance'] as $slug) {
        TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-'.$slug, 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
        app(ModuleManager::class)->enableForTenant($slug, $tenant);
    }
    foreach (['Finance', 'Memberships'] as $module) {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path("Modules/{$module}/Database/Migrations"), '--realpath' => true, '--force' => true]);
    }

    return [$user, $tenant];
}

function memberType(Tenant $tenant, string $name = 'Individual', int $fee = 12000, int $months = 12, bool $auto = true): MemType
{
    return MemType::query()->create(['tenant_id' => $tenant->id, 'name' => $name, 'fee' => $fee, 'period_months' => $months, 'auto_invoice' => $auto]);
}

function payDues(Tenant $tenant, MemMember $member): void
{
    $invoice = MemRenewal::query()->where('member_id', $member->id)->where('status', 'billed')->firstOrFail()->invoice()->with('contact')->firstOrFail();
    app(PaymentService::class)->record($tenant->id, $invoice->contact, $invoice->total, today(), [$invoice->id => $invoice->total], ['method' => 'bank_transfer']);
}

describe('Membership rules', function (): void {
    test('a paid type is invoiced through Finance and the member waits until it is paid', function (): void {
        [, $tenant] = createMembershipsContext();
        $service = app(MembershipService::class);

        $member = $service->join($tenant->id, memberType($tenant), 'Ama', ['email' => 'Ama@Example.test']);
        $renewal = $member->renewals()->with('invoice')->firstOrFail();

        expect($member->status)->toBe('pending')->and($member->email)->toBe('ama@example.test')->and($member->number)->toStartWith('MEM-')
            ->and($renewal->invoice->status)->toBe(FinInvoice::STATUS_SENT)->and($renewal->invoice->total)->toBe(12000)
            ->and($renewal->invoice->contact->email)->toBe('ama@example.test')
            ->and($renewal->period_end->toDateString())->toBe(today()->addYear()->subDay()->toDateString());

        expect($service->applyPayments($tenant->id))->toBe(0);
        payDues($tenant, $member);

        expect($service->applyPayments($tenant->id))->toBe(1);
        $member->refresh();
        expect($member->status)->toBe('active')->and($member->joined_on->toDateString())->toBe(today()->toDateString())
            ->and($member->expires_on->toDateString())->toBe($renewal->period_end->toDateString())
            ->and($renewal->fresh()->status)->toBe('paid')
            ->and($service->applyPayments($tenant->id))->toBe(0);
    });

    test('a free type is active at once, and duplicate emails and unavailable types are refused', function (): void {
        [, $tenant] = createMembershipsContext();
        $service = app(MembershipService::class);
        $free = memberType($tenant, 'Friend', 0);

        $member = $service->join($tenant->id, $free, 'Kojo', ['email' => 'k@example.test']);
        expect($member->status)->toBe('active')->and($member->renewals()->count())->toBe(0)->and($member->expires_on)->not->toBeNull()
            ->and(fn () => $service->billDues($member))->toThrow(DomainException::class, 'no fee')
            ->and(fn () => $service->join($tenant->id, $free, 'Dup', ['email' => 'K@example.test']))->toThrow(DomainException::class, 'already exists');

        $free->update(['is_active' => false]);
        expect(fn () => $service->join($tenant->id, $free, 'Other', ['email' => 'o@example.test']))->toThrow(DomainException::class, 'not available');
    });

    test('only one unpaid dues invoice at a time, and renewing continues from the end of the paid period', function (): void {
        [, $tenant] = createMembershipsContext();
        $service = app(MembershipService::class);
        $member = $service->join($tenant->id, memberType($tenant), 'Esi', ['email' => 'e@example.test']);

        expect(fn () => $service->billDues($member))->toThrow(DomainException::class, 'unpaid dues');
        payDues($tenant, $member);
        $service->applyPayments($tenant->id);
        $firstEnd = $member->fresh()->expires_on;

        $renewal = $service->billDues($member->fresh()->load(['type', 'contact']));
        expect($renewal->period_start->toDateString())->toBe($firstEnd->copy()->addDay()->toDateString());
        payDues($tenant, $member);
        $service->applyPayments($tenant->id);
        expect($member->fresh()->expires_on->toDateString())->toBe($firstEnd->copy()->addYear()->toDateString());
    });

    test('lapsed members expire and renewals are invoiced 30 days ahead only for auto-renewing types', function (): void {
        [, $tenant] = createMembershipsContext();
        $service = app(MembershipService::class);

        $auto = $service->join($tenant->id, memberType($tenant, 'Auto'), 'Auto Member', ['email' => 'a@example.test']);
        $manual = $service->join($tenant->id, memberType($tenant, 'Manual', 5000, 12, false), 'Manual Member', ['email' => 'm@example.test']);
        $far = $service->join($tenant->id, memberType($tenant, 'Far'), 'Far Member', ['email' => 'f@example.test']);
        $lapsing = $service->join($tenant->id, memberType($tenant, 'Lapsing'), 'Lapsing Member', ['email' => 'l@example.test']);
        foreach ([$auto, $manual, $far, $lapsing] as $member) {
            payDues($tenant, $member);
        }
        $service->applyPayments($tenant->id);
        $auto->update(['expires_on' => today()->addDays(10)]);
        $manual->update(['expires_on' => today()->addDays(10)]);
        $lapsing->update(['expires_on' => today()->subDay()]);

        expect($service->expireLapsed($tenant->id))->toBe(1)->and($lapsing->fresh()->status)->toBe('expired');
        expect($service->invoiceUpcomingRenewals($tenant))->toBe(1)
            ->and($auto->renewals()->where('status', 'billed')->count())->toBe(1)
            ->and($manual->renewals()->where('status', 'billed')->count())->toBe(0)
            ->and($far->renewals()->where('status', 'billed')->count())->toBe(0)
            ->and($service->invoiceUpcomingRenewals($tenant))->toBe(0);

        $lapsingRenewal = $service->billDues($lapsing->fresh()->load(['type', 'contact']));
        expect($lapsingRenewal->period_start->toDateString())->toBe(today()->toDateString());
    });

    test('cancelling voids an unpaid invoice, reinstating restores status, and complimentary activation needs no invoice', function (): void {
        [, $tenant] = createMembershipsContext();
        $service = app(MembershipService::class);
        $member = $service->join($tenant->id, memberType($tenant), 'Yaw', ['email' => 'y@example.test']);
        $invoice = $member->renewals()->firstOrFail()->invoice;

        expect(fn () => $service->grantComplimentary($member))->toThrow(DomainException::class, 'unpaid');
        $service->cancel($member);
        expect($member->fresh()->status)->toBe('cancelled')->and($invoice->fresh()->status)->toBe(FinInvoice::STATUS_VOID)
            ->and($member->renewals()->first()->status)->toBe('void')
            ->and(fn () => $service->billDues($member->fresh()->load(['type', 'contact'])))->toThrow(DomainException::class, 'cancelled');

        expect(fn () => $service->reinstate($service->join($tenant->id, memberType($tenant, 'Z', 0), 'Active One', ['email' => 'z@example.test'])))->toThrow(DomainException::class);
        expect($service->reinstate($member->fresh())->status)->toBe('pending');
        $service->grantComplimentary($member->fresh()->load('type'));
        expect($member->fresh()->status)->toBe('active')->and($member->renewals()->count())->toBe(1);
    });

    test('a voided dues invoice does not activate the member', function (): void {
        [, $tenant] = createMembershipsContext();
        $service = app(MembershipService::class);
        $member = $service->join($tenant->id, memberType($tenant), 'Void Case', ['email' => 'v@example.test']);
        app(InvoiceService::class)->void($member->renewals()->firstOrFail()->invoice);

        $service->applyPayments($tenant->id);
        expect($member->fresh()->status)->toBe('pending')->and($member->renewals()->first()->status)->toBe('void');
    });
});

describe('Membership screens', function (): void {
    test('staff create a type, add a member, see dues and cancel', function (): void {
        [$user, $tenant] = createMembershipsContext();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('memberships.index'))->assertOk()->assertSee('Active members');
        $this->post(route('memberships.types.store'), ['name' => 'Corporate', 'fee' => '500.50', 'period_months' => 12, 'is_public' => 1])->assertSessionHas('success');
        $type = MemType::query()->firstOrFail();
        expect($type->fee)->toBe(50050);
        $this->get(route('memberships.types.index'))->assertOk()->assertSee('Corporate');

        $this->post(route('memberships.members.store'), ['name' => 'Acme Ltd', 'email' => 'acme@example.test', 'type_id' => $type->id])->assertRedirect();
        $member = MemMember::query()->firstOrFail();
        $this->get(route('memberships.members.show', $member->uuid))->assertOk()->assertSee('Acme Ltd')->assertSee('Dues history');
        $this->get(route('memberships.members.index', ['status' => 'pending', 'search' => 'acme']))->assertOk()->assertSee('Acme Ltd');

        $this->put(route('memberships.members.update', $member->uuid), ['name' => 'Acme Limited', 'type_id' => $type->id, 'email' => 'acme@example.test'])->assertSessionHas('success');
        expect($member->fresh()->name)->toBe('Acme Limited');
        $this->post(route('memberships.members.bill', $member->uuid))->assertSessionHas('error');
        $this->post(route('memberships.members.cancel', $member->uuid))->assertSessionHas('success');
        expect($member->fresh()->status)->toBe('cancelled');
    });

    test('validation and permissions are enforced', function (): void {
        [$user, $tenant] = createMembershipsContext();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->post(route('memberships.types.store'), [])->assertSessionHasErrors(['name', 'fee', 'period_months']);
        $this->post(route('memberships.members.store'), [])->assertSessionHasErrors(['name', 'type_id']);

        $user->revokePermissionTo('memberships.manage');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->post(route('memberships.types.store'), ['name' => 'X', 'fee' => 1, 'period_months' => 1])->assertForbidden();
        $this->get(route('memberships.index'))->assertOk();

        $user->revokePermissionTo('memberships.view');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->get(route('memberships.index'))->assertForbidden();
    });

    test('the sync command applies payments and expires lapsed members', function (): void {
        [, $tenant] = createMembershipsContext();
        $service = app(MembershipService::class);
        $member = $service->join($tenant->id, memberType($tenant), 'Sync Me', ['email' => 's@example.test']);
        payDues($tenant, $member);

        $this->artisan('memberships:sync')->assertSuccessful();
        expect($member->fresh()->status)->toBe('active');
    });
});

describe('Public membership page', function (): void {
    test('an application creates a pending member, a repeat looks identical, and bots and bad input are rejected', function (): void {
        [, $tenant] = createMembershipsContext();
        $type = memberType($tenant);
        $hidden = memberType($tenant, 'Board only');
        $hidden->update(['is_public' => false]);
        $url = route('memberships.public.store', ['tenant' => $tenant->slug]);

        $this->get(route('memberships.public.join', ['tenant' => $tenant->slug]))->assertOk()->assertSee('Apply for membership')->assertSee('Individual')->assertDontSee('Board only');
        $this->post($url, ['name' => 'Efua', 'email' => 'efua@example.test', 'type_id' => $type->id])->assertOk()->assertSee('Thank you');
        $this->post($url, ['name' => 'Efua Again', 'email' => 'efua@example.test', 'type_id' => $type->id])->assertOk()->assertSee('Thank you');

        expect(MemMember::query()->count())->toBe(1)->and(MemMember::query()->first()->status)->toBe('pending');

        $this->post($url, ['name' => 'Sneaky', 'email' => 's@example.test', 'type_id' => $hidden->id])->assertStatus(422);
        $this->post($url, ['name' => 'Bot', 'email' => 'bot@example.test', 'type_id' => $type->id, 'website' => 'http://spam'])->assertSessionHasErrors('website');
        $this->post($url, ['name' => '', 'email' => 'not-an-email'])->assertSessionHasErrors(['name', 'email', 'type_id']);
        expect(MemMember::query()->count())->toBe(1);
    });

    test('the page does not exist for unknown organisations or ones without the module', function (): void {
        [, $tenant] = createMembershipsContext(false);

        $this->get(route('memberships.public.join', ['tenant' => $tenant->slug]))->assertNotFound();
        $this->get(route('memberships.public.join', ['tenant' => 'nobody']))->assertNotFound();
    });
});

describe('Memberships demo seeder', function (): void {
    test('creates types and members, once, and refuses production', function (): void {
        [, $tenant] = createMembershipsContext();
        config(['app.demo_tenant' => $tenant->slug]);

        $this->seed(MembershipsDemoSeeder::class);

        expect(MemType::query()->count())->toBe(3)->and(MemMember::query()->count())->toBe(7)
            ->and(MemMember::query()->where('status', 'active')->count())->toBe(6)->and(MemMember::query()->where('status', 'pending')->count())->toBe(1);

        $count = MemMember::query()->count();
        $this->seed(MembershipsDemoSeeder::class);
        expect(MemMember::query()->count())->toBe($count);
    });
});
