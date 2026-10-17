<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\CmsCore\Support\EnquiryToken;
use App\Modules\Crm\Models\CrmAccount;
use App\Modules\Crm\Models\CrmContact;
use App\Modules\Crm\Models\CrmDeal;
use App\Modules\Crm\Models\CrmLead;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createCrmTestContext(): array
{
    $user = User::factory()->create([
        'first_name' => 'Crm',
        'last_name' => 'Director',
    ]);
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [
        'crm.view',
        'crm.leads.manage',
        'crm.accounts.manage',
        'crm.deals.manage',
        'crm.convert',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ], [
            'category' => 'crm',
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $user->givePermissionTo($permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    app(ModuleManager::class)->enableForTenant('crm', $tenant);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/Crm/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    return [$user, $tenant];
}

describe('Phase 18: Enterprise CRM Module & Lead-to-Client Conversion', function (): void {
    test('captures walk-in inquiries, converts lead into client account + portal user, and manages deal stages', function (): void {
        [$user, $tenant] = createCrmTestContext();

        // 1. Capture Walk-In Lead
        $storeLeadResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('crm.leads.store'), [
                'full_name' => 'Nana Ama McBrown',
                'email' => 'nana.ama@client-estate.com',
                'phone' => '+233244112233',
                'organisation' => 'McBrown Holdings Ltd',
                'source' => CrmLead::SOURCE_WALK_IN,
                'message' => 'Walked into office to inquire about 4 serviced plots at East Legon Hills.',
            ]);

        $storeLeadResponse->assertRedirect(route('crm.index'));

        $lead = CrmLead::query()->where('email', 'nana.ama@client-estate.com')->firstOrFail();
        expect($lead->source)->toBe(CrmLead::SOURCE_WALK_IN)
            ->and($lead->status)->toBe(CrmLead::STATUS_NEW);

        // 2. Convert Lead to Client Account + Contact + Portal User
        $convertResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('crm.leads.convert', $lead), [
                'provision_portal_user' => '1',
            ]);

        $convertResponse->assertRedirect(route('crm.index'));

        $lead->refresh();
        expect($lead->status)->toBe(CrmLead::STATUS_CONVERTED)
            ->and($lead->converted_account_id)->not->toBeNull()
            ->and($lead->converted_contact_id)->not->toBeNull();

        $account = CrmAccount::query()->findOrFail($lead->converted_account_id);
        $contact = CrmContact::query()->findOrFail($lead->converted_contact_id);
        $portalUser = User::query()->where('email', 'nana.ama@client-estate.com')->first();

        expect($account->name)->toBe('McBrown Holdings Ltd')
            ->and($account->portal_user_id)->toBe((string) $portalUser?->uuid)
            ->and($contact->is_primary)->toBeTrue();

        // Double conversion is rejected
        $doubleConvert = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('crm.leads.convert', $lead));
        $doubleConvert->assertSessionHasErrors('lead');

        // 3. Create Deal in GHS and transition stage
        $storeDealResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('crm.deals.store'), [
                'account_id' => $account->id,
                'title' => '4 Serviced Plots Acquisition - East Legon Hills',
                'stage' => CrmDeal::STAGE_SITE_VISIT,
                'amount_ghs' => 480000.50,
                'probability' => 35,
                'expected_close_date' => '2026-12-15',
            ]);

        $storeDealResponse->assertRedirect(route('crm.index'));

        $deal = CrmDeal::query()->where('account_id', $account->id)->firstOrFail();
        expect($deal->value_pesewas)->toBe(48000050)
            ->and($deal->formattedValue())->toBe('GHS 480,000.50');

        // Transition Deal to Closed Won
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('crm.deals.stage', $deal), [
                'stage' => CrmDeal::STAGE_CLOSED_WON,
            ])
            ->assertRedirect(route('crm.index'));

        expect($deal->fresh()?->stage)->toBe(CrmDeal::STAGE_CLOSED_WON)
            ->and($deal->fresh()?->probability)->toBe(100);

        // 4. Verify CRM Hub renders all metrics and records in d/m/Y format
        $hubResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('crm.index'));

        $hubResponse->assertOk()
            ->assertSee('CRM &amp; Client Pipeline Hub', false)
            ->assertSee('McBrown Holdings Ltd')
            ->assertSee('480,000.50')
            ->assertSee('15/12/2026');
    });

    test('companion CmsEnquiryController automatically persists public website enquiries into CrmLead', function (): void {
        Mail::fake();

        [$user, $tenant] = createCrmTestContext();
        app(ModuleManager::class)->enableForTenant('cms-core', $tenant);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => app_path('Modules/CmsCore/Database/Migrations'),
            '--realpath' => true,
            '--force' => true,
        ]);

        $tenant->update(['email' => 'enquiries@tenant-crm-test.org']);
        $token = EnquiryToken::issue(time() - 10);

        $response = $this->withSession(['active_tenant_id' => $tenant->id])
            ->postJson(route('cms-core.public.enquiries.store'), [
                'type' => 'partnership',
                'name' => 'Dr. Samuel Ofori',
                'email' => 'samuel.ofori@hospital-partner.org',
                'phone' => '+233209988776',
                'organisation' => 'Accra Specialist Clinic',
                'message' => 'Interested in institutional real estate and facility management partnership.',
                'form_token' => $token,
                'website' => '',
            ]);

        $response->assertOk()->assertJsonPath('sent', true);

        $lead = CrmLead::query()->where('email', 'samuel.ofori@hospital-partner.org')->first();
        expect($lead)->not->toBeNull()
            ->and($lead?->source)->toBe(CrmLead::SOURCE_WEBSITE_ENQUIRY)
            ->and($lead?->enquiry_type)->toBe('partnership')
            ->and($lead?->organisation)->toBe('Accra Specialist Clinic');
    });

    test('adversarial negative tests enforce RBAC and cross-tenant isolation on CRM records', function (): void {
        [$userA, $tenantA] = createCrmTestContext();

        $otherTenant = Tenant::factory()->create(['isolation_mode' => 'shared']);
        $foreignAccount = CrmAccount::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Tenant Account',
            'code' => 'ACC-FOREIGN',
            'account_type' => 'corporate',
            'status' => 'active',
        ]);
        $foreignLead = CrmLead::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'reference_code' => 'LEAD-FOREIGN',
            'source' => CrmLead::SOURCE_WALK_IN,
            'full_name' => 'Foreign Prospect',
            'status' => CrmLead::STATUS_NEW,
        ]);

        // Cross-tenant lead conversion returns 404
        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->post(route('crm.leads.convert', ['lead' => $foreignLead->uuid]))
            ->assertNotFound();

        // Cross-tenant account_id on deal creation returns 422
        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->post(route('crm.deals.store'), [
                'account_id' => $foreignAccount->id,
                'title' => 'Attempted Cross-Tenant Deal',
                'stage' => CrmDeal::STAGE_DISCOVERY,
                'amount_ghs' => 10000,
                'probability' => 20,
            ])
            ->assertSessionHasErrors('account_id');

        // Unpermitted user receives 403
        $unpermitted = User::factory()->create(['tenant_id' => $tenantA->id]);
        $unpermitted->tenants()->syncWithoutDetaching([$tenantA->id]);

        $this->actingAs($unpermitted)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->get(route('crm.index'))
            ->assertForbidden();
    });
});
