<?php

declare(strict_types=1);

use App\Enum\UsageMetric;
use App\Models\Tenant;
use App\Models\UsageRollup;
use App\Models\User;
use App\Services\Tenancy\BillingAccessService;
use App\Services\Tenancy\BillingAgreementService;
use App\Services\Tenancy\FeatureService;
use App\Services\Tenancy\InvoicingService;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-01 00:00:00'));
    $this->tenant = Tenant::factory()->create(['country' => 'GH']);
});

it('invoices independent USD rates and excludes prepaid emails and included resources', function (): void {
    $this->tenant->update(['country' => 'US']);
    app(BillingAgreementService::class)->schedule($this->tenant, 'managed_business', [], 'USD', 5, true, now());
    foreach ([['billing_mode' => 'postpaid'], ['billing_mode' => 'prepaid']] as $dimensions) {
        UsageRollup::create(['tenant_id' => $this->tenant->id, 'period' => 'day', 'period_start' => now(), 'metric' => UsageMetric::EMAIL_COUNT, 'value' => 1000, 'dimensions' => $dimensions]);
    }
    config(['usage-pricing.meters.emails.count.USD' => 999]);
    $this->travelTo(Carbon::parse('2026-11-01'));
    $invoice = app(InvoicingService::class)->generate($this->tenant, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')->endOfDay());
    expect($invoice->currency)->toBe('USD')->and((float) $invoice->subtotal)->toBe(151.0);
});

it('provides custom domains without leaking legacy commerce grants', function (): void {
    app(BillingAgreementService::class)->schedule($this->tenant, 'essentials', [], 'GHS', 2, false, now());
    app(TenantContext::class)->setTenant($this->tenant);
    $features = app(FeatureService::class);
    $features->enable('commerce');
    expect($features->enabled('custom-domains'))->toBeTrue()->and($features->enabled('commerce'))->toBeFalse();
});

it('revokes licensing without removing already earned monthly active charges', function (): void {
    app(BillingAgreementService::class)->schedule($this->tenant, 'essentials', [], 'GHS', 2, false, now());
    $user = User::factory()->create();
    $this->tenant->users()->attach($user);
    $access = app(BillingAccessService::class);
    $access->assign($this->tenant, $user, 'operational');
    $access->recordActivity($this->tenant, $user);
    $access->revoke($this->tenant, $user);
    expect($access->level($this->tenant, $user))->toBeNull()
        ->and($access->counts($this->tenant, now()))->toBe(['self_service' => 0, 'operational' => 1]);
});

it('keeps historical invoice currency even after account billing-country changes', function (): void {
    app(BillingAgreementService::class)->schedule($this->tenant, 'essentials', [], 'GHS', 2, false, now());
    $this->tenant->update(['country' => 'US']);
    $this->travelTo(Carbon::parse('2026-11-01'));
    $invoice = app(InvoicingService::class)->generate($this->tenant, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')->endOfDay());
    expect($invoice->currency)->toBe('GHS')->and((float) $invoice->subtotal)->toBe(300.0);
});

it('releases editor capacity when a person leaves the organisation', function (): void {
    app(BillingAgreementService::class)->schedule($this->tenant, 'essentials', [], 'GHS', 2, false, now());
    $members = User::factory()->count(3)->create();
    $this->tenant->users()->attach($members->modelKeys());
    $access = app(BillingAccessService::class);
    $access->assign($this->tenant, $members[0], 'website_editor');
    $access->assign($this->tenant, $members[1], 'website_editor');
    $this->tenant->users()->detach($members[0]);
    $access->assign($this->tenant, $members[2], 'website_editor');
    expect($access->level($this->tenant, $members[2]))->toBe('website_editor');
});

it('records the admitted category and month despite a concurrent downgrade or revocation', function (): void {
    app(BillingAgreementService::class)->schedule($this->tenant, 'essentials', [], 'GHS', 2, false, now());
    $user = User::factory()->create();
    $this->tenant->users()->attach($user);
    $access = app(BillingAccessService::class);
    $access->assign($this->tenant, $user, 'operational');
    $month = now()->startOfMonth();
    $access->revoke($this->tenant, $user);
    $this->travelTo(Carbon::parse('2026-11-01'));
    $access->recordActivity($this->tenant, $user, 'operational', $month);
    expect($access->counts($this->tenant, $month))->toBe(['self_service' => 0, 'operational' => 1])
        ->and($access->counts($this->tenant, now()))->toBe(['self_service' => 0, 'operational' => 0]);
});

it('does not treat internal or non-public CMS media as a public licence exemption', function (bool $internal, bool $public): void {
    app(BillingAgreementService::class)->schedule($this->tenant, 'essentials', [], 'GHS', 2, false, now());
    $user = User::factory()->create();
    $this->tenant->users()->attach($user);
    app(TenantContext::class)->setTenant($this->tenant);
    $request = Illuminate\Http\Request::create('/cms-core/media/serve/private');
    $request->setUserResolver(fn () => $user);
    $route = new Illuminate\Routing\Route(['GET'], '/cms-core/media/serve/{media}', [App\Modules\CmsCore\Http\Controllers\Web\CmsMediaServeController::class, 'show']);
    $route->setContainer(app());
    $route->name('cms-core.media.serve');
    $route->bind($request);
    $route->setParameter('media', new App\Modules\CmsCore\Models\Media(['tenant_id' => $this->tenant->id, 'is_internal' => $internal, 'is_public' => $public]));
    $request->setRouteResolver(fn () => $route);
    app(App\Http\Middleware\EnforceBillingAccess::class)->handle($request, fn () => response('private'));
})->with([[true, true], [false, false]])->throws(Symfony\Component\HttpKernel\Exception\HttpException::class, 'Billing access has not been assigned');
