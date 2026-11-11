<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantModule;
use App\Models\TenantPaymentGateway;
use App\Models\User;
use App\Modules\Donations\Models\DonCampaign;
use App\Modules\Memberships\Models\MemMember;
use App\Modules\Memberships\Models\MemType;
use App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidGhanaSeeder;
use App\Modules\SiteThyroidGhanaFoundation\Demo\Sections\MembershipTiersSection;
use App\Modules\Volunteers\Models\VolOpportunity;
use App\Modules\Volunteers\Models\VolVolunteer;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const TGF_SITE = 'https://thyroidghanafoundation.org';

function tgfSiteTenant(): Tenant
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user, ['slug' => 'thyroid-ghana-foundation', 'custom_domain' => 'thyroidghanafoundation.org', 'custom_domain_status' => 'active']);
    app(TenantDatabaseManager::class)->configure($tenant);

    foreach (['module-cms-core', 'custom-domains'] as $feature) {
        TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => $feature, 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
    }
    foreach (['cms-core', 'site-thyroid-ghana-foundation'] as $module) {
        TenantModule::query()->create(['tenant_id' => $tenant->id, 'module_slug' => $module, 'is_enabled' => true, 'version' => '1.0.0']);
    }
    $modules = ['finance' => 'Finance', 'memberships' => 'Memberships', 'donations' => 'Donations', 'volunteers' => 'Volunteers'];
    foreach (array_keys($modules) as $slug) {
        TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-'.$slug, 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
        app(ModuleManager::class)->enableForTenant($slug, $tenant);
    }
    foreach ($modules as $directory) {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path("Modules/{$directory}/Database/Migrations"), '--realpath' => true, '--force' => true]);
    }
    Cache::flush();
    app(ThyroidGhanaSeeder::class)->run();

    return $tenant;
}

describe('Membership page', function (): void {
    test('shows the three real tiers with their fees and hides demo types', function (): void {
        $tenant = tgfSiteTenant();
        (new MembershipTiersSection)->ensure($tenant);
        MemType::query()->create(['tenant_id' => $tenant->id, 'name' => 'Demo: Hidden tier', 'fee' => 99_900, 'period_months' => 12, 'is_public' => true]);

        $html = $this->get(TGF_SITE.'/membership')->assertOk()->getContent();

        expect($html)->toContain('Student and Patient Member')->toContain('GHS 100')->toContain('Individual Member')->toContain('GHS 200')
            ->toContain('Corporate Partner')->toContain('GHS 2,000')->not->toContain('Demo: Hidden tier')->not->toContain('Demo:');
    });

    test('falls back to the enquiry form when there are no real public tiers', function (): void {
        $tenant = tgfSiteTenant();
        MemType::query()->create(['tenant_id' => $tenant->id, 'name' => 'Demo: Only a demo', 'fee' => 10_000, 'period_months' => 12, 'is_public' => true]);

        $html = $this->get(TGF_SITE.'/membership')->assertOk()->getContent();

        expect($html)->toContain('enquiry')->not->toContain('Only a demo')->not->toContain('name="type_id"');
    });

    test('a valid application creates a pending member and a repeat looks the same', function (): void {
        $tenant = tgfSiteTenant();
        (new MembershipTiersSection)->ensure($tenant);
        $type = MemType::query()->where('tenant_id', $tenant->id)->where('name', 'Individual Member')->firstOrFail();
        $payload = ['name' => 'Ama Visitor', 'email' => 'ama.visitor@example.com', 'phone' => '0240000000', 'type_id' => $type->id];

        $this->post(TGF_SITE.'/membership', $payload)->assertOk()->assertSee('Thank you');
        $this->post(TGF_SITE.'/membership', $payload)->assertOk()->assertSee('Thank you');

        expect(MemMember::query()->where('tenant_id', $tenant->id)->where('email', 'ama.visitor@example.com')->count())->toBe(1)
            ->and(MemMember::query()->where('tenant_id', $tenant->id)->value('status'))->toBe('pending');
    });

    test('a filled honeypot field creates nothing', function (): void {
        $tenant = tgfSiteTenant();
        (new MembershipTiersSection)->ensure($tenant);
        $type = MemType::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->post(TGF_SITE.'/membership', ['name' => 'Bot', 'email' => 'bot@example.com', 'type_id' => $type->id, 'website' => 'http://spam.test'])->assertSessionHasErrors('website');

        expect(MemMember::query()->where('tenant_id', $tenant->id)->count())->toBe(0);
    });

    test('is throttled after ten applications in a minute', function (): void {
        $tenant = tgfSiteTenant();
        (new MembershipTiersSection)->ensure($tenant);
        $type = MemType::query()->where('tenant_id', $tenant->id)->firstOrFail();

        foreach (range(1, 10) as $i) {
            $this->post(TGF_SITE.'/membership', ['name' => "Visitor {$i}", 'email' => "v{$i}@example.com", 'type_id' => $type->id])->assertOk();
        }

        $this->post(TGF_SITE.'/membership', ['name' => 'One more', 'email' => 'more@example.com', 'type_id' => $type->id])->assertStatus(429);
    });

    test('a type id from another tenant is rejected and nothing is created for that tenant', function (): void {
        $tenant = tgfSiteTenant();
        $other = Tenant::factory()->create(['slug' => 'someone-else', 'isolation_mode' => 'shared']);
        $foreign = MemType::query()->create(['tenant_id' => $other->id, 'name' => 'Foreign tier', 'fee' => 10_000, 'period_months' => 12, 'is_public' => true]);

        $this->post(TGF_SITE.'/membership', ['name' => 'Sneaky', 'email' => 'sneaky@example.com', 'type_id' => $foreign->id])->assertStatus(422);

        expect(MemMember::query()->whereIn('tenant_id', [$tenant->id, $other->id])->count())->toBe(0);
    });
});

describe('Donate page', function (): void {
    test('hides demo campaigns and offers contact details and the enquiry form while online giving is off', function (): void {
        $tenant = tgfSiteTenant();
        DonCampaign::query()->create(['tenant_id' => $tenant->id, 'name' => 'Demo: Annual giving 2026', 'goal_amount' => 5_000_000, 'starts_on' => today()->startOfYear()]);

        $html = $this->get(TGF_SITE.'/donate')->assertOk()->getContent();

        expect($html)->not->toContain('Demo:')->toContain('info@thyroidghanafoundation.org')->toContain('enquiry')->not->toContain('name="amount"');
    });

    test('shows the gift form when online giving is on and sends the donor to checkout', function (): void {
        $tenant = tgfSiteTenant();
        TenantPaymentGateway::query()->withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'provider' => 'paystack', 'api_key_encrypted' => 'sk_test_give', 'public_key_encrypted' => 'pk_test', 'is_active' => true]);
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['data' => ['authorization_url' => 'https://checkout.paystack.test/abc', 'reference' => 'ref_give']])]);

        $this->get(TGF_SITE.'/donate')->assertOk()->assertSee('name="amount"', false);
        $this->post(TGF_SITE.'/donate', ['name' => 'Kind Donor', 'email' => 'donor@example.com', 'amount' => 100])->assertRedirect('https://checkout.paystack.test/abc');
    });
});

describe('Volunteer page', function (): void {
    test('lists real open opportunities and never demo ones', function (): void {
        $tenant = tgfSiteTenant();
        VolOpportunity::query()->create(['tenant_id' => $tenant->id, 'title' => 'Demo: Screening camp, Kumasi', 'location' => 'Kumasi', 'starts_on' => today()->addDays(9), 'spots' => 4, 'status' => 'open']);
        VolOpportunity::query()->create(['tenant_id' => $tenant->id, 'title' => 'Patient forum helpers', 'location' => 'Accra', 'starts_on' => today()->addDays(5), 'spots' => 3, 'status' => 'open']);

        $html = $this->get(TGF_SITE.'/volunteer')->assertOk()->getContent();

        expect($html)->toContain('Patient forum helpers')->not->toContain('Demo:');
    });

    test('a sign-up creates a volunteer waiting for approval', function (): void {
        $tenant = tgfSiteTenant();

        $this->post(TGF_SITE.'/volunteer', ['name' => 'Helpful Person', 'email' => 'helper@example.com', 'skills' => 'Driving'])->assertOk()->assertSee('Thank you');

        $volunteer = VolVolunteer::query()->where('tenant_id', $tenant->id)->where('email', 'helper@example.com')->firstOrFail();
        expect($volunteer->status)->toBe('pending');
    });
});

describe('Partner page and sign-in links', function (): void {
    test('the partner page shows the partnership enquiry form', function (): void {
        tgfSiteTenant();

        $this->get(TGF_SITE.'/partner-us')->assertOk()->assertSee('enquiry', false);
    });

    test('the footer links to the member and volunteer sign-in', function (): void {
        tgfSiteTenant();

        $html = $this->get(TGF_SITE.'/membership')->assertOk()->getContent();

        expect($html)->toContain('/join/thyroid-ghana-foundation/portal')->toContain('/volunteer/thyroid-ghana-foundation/portal');
    });
});
