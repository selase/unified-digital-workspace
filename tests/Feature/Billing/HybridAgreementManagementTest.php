<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\TwoFactorChallenge;
use App\Models\Tenant;
use App\Models\TenantBillingAccess;
use App\Models\TenantBillingAgreement;
use App\Models\User;
use App\Services\Tenancy\BillingAgreementService;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 20)->startOfDay());
    $this->withoutMiddleware([EnsureOnboardingComplete::class, TwoFactorChallenge::class]);
    $this->admin = User::factory()->create();
    $this->tenant = setActiveTenantForTest($this->admin, ['country' => 'Ghana']);
    Gate::before(fn (User $user, string $ability): ?bool => $ability === 'admin.tenants.update' ? $user->is($this->admin) : null);
    $this->payload = [
        'plan_slug' => 'essentials', 'currency' => 'GHS', 'package_slugs' => ['hr'],
        'editor_seats' => 2, 'setup_required' => true, 'confirm_adoption' => '1', 'effective_from' => '2026-11-01',
    ];
});

it('shows the agreement adoption page using the tenants parallel uuid', function (): void {
    $this->actingAs($this->admin)->get(route('tenants.billing-agreements.index', $this->tenant->uuid))
        ->assertOk()->assertSee('No active hybrid agreement')->assertSee('Confirm adoption and schedule agreement');
});

it('requires landlord permission for every agreement management action', function (): void {
    $member = User::factory()->create();
    $this->tenant->users()->attach($member);
    grantOrganisationAdminAccess($member);
    $this->actingAs($member);
    $this->get(route('tenants.billing-agreements.index', $this->tenant->uuid))->assertForbidden();
    $this->post(route('tenants.billing-agreements.store', $this->tenant->uuid), $this->payload)->assertForbidden();
    $this->put(route('tenants.billing-access.update', [$this->tenant->uuid, $member]), ['level' => 'operational'])->assertForbidden();
    expect(TenantBillingAgreement::query()->count())->toBe(0);
});

it('schedules a real immutable rate agreement without changing legacy packages', function (): void {
    $packageId = $this->tenant->package_id;
    $this->actingAs($this->admin)->post(route('tenants.billing-agreements.store', $this->tenant->uuid), $this->payload)
        ->assertRedirect(route('tenants.billing-agreements.index', $this->tenant->uuid))->assertSessionHasNoErrors();
    $agreement = TenantBillingAgreement::query()->where('tenant_id', $this->tenant->id)->sole();
    expect($agreement->currency)->toBe('GHS')
        ->and($agreement->snapshot['hybrid'])->toEqual(config('usage-pricing.hybrid'))
        ->and($agreement->snapshot['selected_packages'])->toBe(['hr'])
        ->and($agreement->setup_required)->toBeTrue()
        ->and($this->tenant->fresh()->package_id)->toBe($packageId)
        ->and(app(BillingAgreementService::class)->activeFor($this->tenant))->toBeNull();
    $this->actingAs($this->admin)->get(route('tenants.billing-agreements.index', $this->tenant->uuid))
        ->assertOk()->assertSee('Scheduled')->assertSee('HR Management');
});

it('rejects invalid adoption inputs', function (string $field, mixed $value): void {
    $this->actingAs($this->admin)->post(route('tenants.billing-agreements.store', $this->tenant->uuid), array_replace($this->payload, [$field => $value]))
        ->assertSessionHasErrors($field);
    expect(TenantBillingAgreement::query()->count())->toBe(0);
})->with([
    ['confirm_adoption', '0'], ['effective_from', '2026-10-01'], ['effective_from', '2026-11-02'],
    ['effective_from', 'invalid'], ['currency', 'USD'], ['editor_seats', 1], ['plan_slug', 'missing'],
    ['setup_required', 'invalid'], ['plan_slug', ['essentials']],
]);

it('validates module subscriptions and included editor seats for managed plans', function (): void {
    $this->actingAs($this->admin)->post(route('tenants.billing-agreements.store', $this->tenant->uuid), array_replace($this->payload, [
        'plan_slug' => 'managed_business', 'editor_seats' => 2, 'package_slugs' => ['missing'],
    ]))->assertSessionHasErrors(['editor_seats', 'package_slugs.0']);
});

it('accepts adoption today only when today is the first day of the month', function (): void {
    $this->travelTo(now()->setDate(2026, 11, 1));
    $unusedTenant = Tenant::factory()->create(['country' => 'GH']);
    $this->actingAs($this->admin)->post(route('tenants.billing-agreements.store', $unusedTenant->uuid), $this->payload)->assertSessionHasNoErrors();
    expect(app(BillingAgreementService::class)->activeFor($unusedTenant))->not->toBeNull();
});

it('preserves an earlier snapshot when a later agreement is scheduled', function (): void {
    $this->actingAs($this->admin)->post(route('tenants.billing-agreements.store', $this->tenant->uuid), $this->payload)->assertSessionHasNoErrors();
    $earlier = TenantBillingAgreement::query()->sole();
    $snapshot = $earlier->snapshot;
    $this->post(route('tenants.billing-agreements.store', $this->tenant->uuid), array_replace($this->payload, [
        'effective_from' => '2026-12-01', 'plan_slug' => 'managed_business', 'editor_seats' => 5, 'setup_required' => false,
    ]))->assertSessionHasNoErrors();
    expect(TenantBillingAgreement::query()->count())->toBe(2)->and($earlier->fresh()->snapshot)->toBe($snapshot);
});

it('assigns member licensing against a pending first agreement without granting roles', function (): void {
    $member = User::factory()->create();
    $this->tenant->users()->attach($member);
    app(BillingAgreementService::class)->schedule($this->tenant, 'essentials', [], 'GHS', 2, false, now()->addMonth()->startOfMonth());
    $roles = $member->roles()->pluck('id')->all();
    $this->actingAs($this->admin)->put(route('tenants.billing-access.update', [$this->tenant->uuid, $member]), ['level' => 'operational'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(TenantBillingAccess::query()->where('tenant_id', $this->tenant->id)->where('user_id', $member->id)->value('level'))->toBe('operational')
        ->and($member->roles()->pluck('id')->all())->toBe($roles);
});

it('rejects access for outsiders and invalid levels', function (): void {
    $outsider = User::factory()->create();
    $this->actingAs($this->admin)->put(route('tenants.billing-access.update', [$this->tenant->uuid, $outsider]), ['level' => 'operational'])->assertNotFound();
    $this->put(route('tenants.billing-access.update', [$this->tenant->uuid, $this->admin]), ['level' => 'invalid'])->assertSessionHasErrors('level');
});

it('allows a landlord administrator to manage another tenant without changing active tenant', function (): void {
    $other = Tenant::factory()->create(['country' => 'Ghana']);
    $this->actingAs($this->admin)->post(route('tenants.billing-agreements.store', $other->uuid), $this->payload)->assertSessionHasNoErrors();
    expect(TenantBillingAgreement::query()->where('tenant_id', $other->id)->count())->toBe(1)
        ->and(TenantBillingAgreement::query()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

it('audits website file readiness through the landlord interface', function (): void {
    Illuminate\Support\Facades\Storage::fake('billing-audit-local');
    config(['filesystems.default' => 'billing-audit-local', 'filesystems.disks.billing-audit-local' => ['driver' => 'local', 'root' => Illuminate\Support\Facades\Storage::disk('billing-audit-local')->path('')], 'filesystems.disks.s3' => null]);
    app(App\Services\Tenancy\TenantStorageManager::class)->configure($this->tenant);
    Illuminate\Support\Facades\Storage::disk('tenant')->put('cms/readiness.txt', '1234567');
    app(BillingAgreementService::class)->schedule($this->tenant, 'essentials', [], 'GHS', 2, false, now()->addMonth()->startOfMonth());
    $this->actingAs($this->admin)->post(route('tenants.billing-capacity.audit', $this->tenant->uuid))->assertRedirect()->assertSessionHasNoErrors();
    expect(App\Models\HybridCapacityCounter::query()->where('tenant_id', $this->tenant->id)->where('meter', 'files')->sole()->bytes)->toBe(7);
    $member = User::factory()->create();
    $this->tenant->users()->attach($member);
    $this->actingAs($member)->post(route('tenants.billing-capacity.audit', $this->tenant->uuid))->assertForbidden();
});

it('rejects malformed tenant and user identifiers before database binding', function (): void {
    $this->actingAs($this->admin)->get('/tenants/not-a-uuid/billing-agreements')->assertNotFound();
    $this->put('/tenants/'.$this->tenant->uuid.'/billing-access/not-a-uuid', ['level' => 'operational'])->assertStatus(405);
});
