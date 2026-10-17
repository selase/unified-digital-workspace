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

    test('crm seeder successfully populates realistic accounts, contacts, leads, deals, and activities', function (): void {
        [$user, $tenant] = createCrmTestContext();

        Artisan::call('db:seed', [
            '--class' => App\Modules\Crm\Database\Seeders\CrmSeeder::class,
        ]);

        expect(CrmAccount::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(3)
            ->and(CrmContact::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(3)
            ->and(CrmLead::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(5)
            ->and(CrmDeal::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(6)
            ->and(App\Modules\Crm\Models\CrmActivity::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(3);

        $lostDeal = CrmDeal::query()->where('tenant_id', $tenant->id)->where('stage', CrmDeal::STAGE_CLOSED_LOST)->first();
        expect($lostDeal)->not->toBeNull()
            ->and($lostDeal?->stage)->toBe(CrmDeal::STAGE_CLOSED_LOST);
    });

    test('leads lifecycle supports index filtering, show details, edit, status transition, activity logging, and soft delete', function (): void {
        [$user, $tenant] = createCrmTestContext();

        $lead = CrmLead::create([
            'tenant_id' => (string) $tenant->id,
            'reference_code' => 'LEAD-FLOW-01',
            'source' => CrmLead::SOURCE_WALK_IN,
            'full_name' => 'Kofi Kingston',
            'email' => 'kofi.kingston@wwe.test',
            'phone' => '+233240001122',
            'organisation' => 'Kingston Sports Foundation',
            'status' => CrmLead::STATUS_NEW,
            'message' => 'Office walk-in requesting 2 serviced residential plots.',
            'assigned_to_id' => (string) $user->uuid,
        ]);

        // 1. Leads Index & Filters
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('crm.leads.index', ['search' => 'Kingston', 'status' => 'new']))
            ->assertOk()
            ->assertSee('Kofi Kingston')
            ->assertSee('LEAD-FLOW-01');

        // 2. Lead Show
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('crm.leads.show', $lead))
            ->assertOk()
            ->assertSee('Kofi Kingston')
            ->assertSee('Prospect Profile');

        // 3. Lead Edit & Update
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('crm.leads.edit', $lead))
            ->assertOk()
            ->assertSee('Edit Lead: LEAD-FLOW-01');

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('crm.leads.update', $lead), [
                'full_name' => 'Kofi Sarkodie Kingston',
                'email' => 'kofi.kingston@wwe.test',
                'phone' => '+233240001122',
                'organisation' => 'Kingston Foundation Ghana',
                'source' => CrmLead::SOURCE_WALK_IN,
                'message' => 'Updated requirements for residential plots.',
            ])
            ->assertRedirect(route('crm.leads.show', $lead));

        expect($lead->fresh()?->full_name)->toBe('Kofi Sarkodie Kingston')
            ->and($lead->fresh()?->organisation)->toBe('Kingston Foundation Ghana');

        // 4. Status Transition
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('crm.leads.status', $lead), [
                'status' => CrmLead::STATUS_QUALIFIED,
            ])
            ->assertRedirect(route('crm.leads.show', $lead));

        expect($lead->fresh()?->status)->toBe(CrmLead::STATUS_QUALIFIED);

        // Verify activity logged for status change
        $statusActivity = App\Modules\Crm\Models\CrmActivity::query()
            ->where('subject_type', CrmLead::class)
            ->where('subject_id', $lead->id)
            ->where('activity_type', 'status_change')
            ->first();
        expect($statusActivity)->not->toBeNull()
            ->and($statusActivity?->summary)->toContain('status moved from new to qualified');

        // 5. Activity Logging
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('crm.leads.activities.store', $lead), [
                'activity_type' => 'call',
                'summary' => 'Phone call with client representative',
                'details' => 'Confirmed budget and scheduled site viewing for next Tuesday.',
            ])
            ->assertRedirect(route('crm.leads.show', $lead));

        $callActivity = App\Modules\Crm\Models\CrmActivity::query()
            ->where('subject_type', CrmLead::class)
            ->where('subject_id', $lead->id)
            ->where('activity_type', 'call')
            ->first();
        expect($callActivity)->not->toBeNull()
            ->and($callActivity?->summary)->toBe('Phone call with client representative');

        // 6. Soft Delete
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('crm.leads.destroy', $lead))
            ->assertRedirect(route('crm.leads.index'));

        expect(CrmLead::query()->find($lead->id))->toBeNull()
            ->and(CrmLead::withTrashed()->find($lead->id))->not->toBeNull();
    });

    test('accounts and contacts lifecycle supports nested contacts, edit, activities, and soft delete', function (): void {
        [$user, $tenant] = createCrmTestContext();

        // 1. Create Account via controller
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('crm.accounts.store'), [
                'name' => 'Volta River Holdings',
                'account_type' => 'corporate',
                'industry' => 'Energy & Utilities',
                'email' => 'commercial@vrh.test',
                'phone' => '+233302778899',
                'address' => 'Electro Volta House, Accra',
                'contact_first_name' => 'Emmanuel',
                'contact_last_name' => 'Antwi',
                'contact_job_title' => 'Head of Procurement',
            ])
            ->assertRedirect(route('crm.index'));

        $account = CrmAccount::query()->where('name', 'Volta River Holdings')->firstOrFail();
        expect($account->contacts()->count())->toBe(1);

        // 2. Accounts Index & Search
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('crm.accounts.index', ['search' => 'Volta', 'account_type' => 'corporate']))
            ->assertOk()
            ->assertSee('Volta River Holdings')
            ->assertSee($account->code);

        // 3. Show & Edit Account
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('crm.accounts.show', $account))
            ->assertOk()
            ->assertSee('Volta River Holdings')
            ->assertSee('Emmanuel Antwi');

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('crm.accounts.edit', $account))
            ->assertOk()
            ->assertSee('Edit Client Account: '.$account->code);

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('crm.accounts.update', $account), [
                'name' => 'Volta River Holdings Ghana PLC',
                'account_type' => 'corporate',
                'industry' => 'Clean Power & Grid Infrastructure',
                'email' => 'corporate@vrh.test',
                'phone' => '+233302778800',
                'address' => 'Electro Volta House, Accra Central',
                'status' => 'active',
            ])
            ->assertRedirect(route('crm.accounts.show', $account));

        expect($account->fresh()?->name)->toBe('Volta River Holdings Ghana PLC');

        // 4. Add Nested Contact to Account
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('crm.accounts.contacts.store', $account), [
                'first_name' => 'Abigail',
                'last_name' => 'Sarpong',
                'email' => 'abigail@vrh.test',
                'phone' => '+233201998877',
                'job_title' => 'Director of Corporate Legal',
                'is_primary' => '1',
            ])
            ->assertRedirect(route('crm.accounts.show', $account));

        expect($account->contacts()->count())->toBe(2);
        $newContact = CrmContact::query()->where('email', 'abigail@vrh.test')->firstOrFail();
        expect($newContact->is_primary)->toBeTrue();

        // 5. Edit Contact (Form render & update)
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('crm.contacts.edit', $newContact))
            ->assertOk()
            ->assertSee('Edit Contact Person')
            ->assertSee('Abigail');

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('crm.contacts.update', $newContact), [
                'first_name' => 'Abigail Nana',
                'last_name' => 'Owusu-Ansah',
                'email' => 'abigail.nana@vrh.test',
                'phone' => '+233201998800',
                'job_title' => 'Chief Legal Officer',
                'is_primary' => '1',
            ])
            ->assertRedirect(route('crm.accounts.show', $account));

        $newContact->refresh();
        expect($newContact->first_name)->toBe('Abigail Nana')
            ->and($newContact->job_title)->toBe('Chief Legal Officer')
            ->and($newContact->email)->toBe('abigail.nana@vrh.test');

        // 6. Delete Contact
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('crm.contacts.destroy', $newContact))
            ->assertRedirect(route('crm.accounts.show', $account));

        expect(CrmContact::query()->find($newContact->id))->toBeNull()
            ->and(CrmContact::withTrashed()->find($newContact->id))->not->toBeNull();

        // 6. Log Activity on Account
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('crm.accounts.activities.store', $account), [
                'activity_type' => 'meeting',
                'summary' => 'In-person meeting at client headquarters',
                'details' => 'Agreed on master service contract terms.',
            ])
            ->assertRedirect(route('crm.accounts.show', $account));

        $accActivity = App\Modules\Crm\Models\CrmActivity::query()
            ->where('subject_type', CrmAccount::class)
            ->where('subject_id', $account->id)
            ->where('activity_type', 'meeting')
            ->first();
        expect($accActivity)->not->toBeNull();

        // 7. Soft Delete Account
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('crm.accounts.destroy', $account))
            ->assertRedirect(route('crm.accounts.index'));

        expect(CrmAccount::query()->find($account->id))->toBeNull()
            ->and(CrmAccount::withTrashed()->find($account->id))->not->toBeNull();
    });

    test('deals lifecycle supports kanban pipeline view, edit, stage progression to closed_lost, and soft delete', function (): void {
        [$user, $tenant] = createCrmTestContext();

        $account = CrmAccount::create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Goldkey Properties',
            'code' => 'ACC-GKEY01',
            'account_type' => 'corporate',
            'status' => 'active',
        ]);

        $deal = CrmDeal::create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => $account->id,
            'title' => 'Prime Commercial Office Space Leasing',
            'stage' => CrmDeal::STAGE_DISCOVERY,
            'currency' => 'GHS',
            'value_pesewas' => 75000000, // GHS 750,000
            'probability' => 20,
            'expected_close_date' => now()->addMonths(2),
            'owner_id' => (string) $user->uuid,
        ]);

        // 1. Kanban Pipeline View
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('crm.pipeline'))
            ->assertOk()
            ->assertSee('CRM Pipeline Kanban Board')
            ->assertSee('Prime Commercial Office Space Leasing')
            ->assertSee('750,000.00');

        // 2. Deals Directory View
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('crm.deals.index', ['search' => 'Office Space']))
            ->assertOk()
            ->assertSee('Prime Commercial Office Space Leasing')
            ->assertSee('Goldkey Properties');

        // 3. Show & Edit Deal
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('crm.deals.show', $deal))
            ->assertOk()
            ->assertSee('Prime Commercial Office Space Leasing')
            ->assertSee('750,000.00');

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('crm.deals.edit', $deal))
            ->assertOk()
            ->assertSee('Edit Deal: Prime Commercial Office Space Leasing');

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('crm.deals.update', $deal), [
                'title' => 'Prime Commercial Office Space Leasing - 3 Floors',
                'stage' => CrmDeal::STAGE_NEGOTIATION,
                'amount_ghs' => 950000.00,
                'probability' => 75,
                'expected_close_date' => now()->addMonth()->format('Y-m-d'),
                'notes' => 'Negotiating maintenance clause.',
            ])
            ->assertRedirect(route('crm.deals.show', $deal));

        expect($deal->fresh()?->title)->toBe('Prime Commercial Office Space Leasing - 3 Floors')
            ->and($deal->fresh()?->value_pesewas)->toBe(95000000)
            ->and($deal->fresh()?->stage)->toBe(CrmDeal::STAGE_NEGOTIATION);

        // 4. Move Stage to Closed Lost using renamed constant
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('crm.deals.stage', $deal), [
                'stage' => CrmDeal::STAGE_CLOSED_LOST,
            ])
            ->assertRedirect();

        expect($deal->fresh()?->stage)->toBe('closed_lost')
            ->and($deal->fresh()?->probability)->toBe(0);

        // 5. Log Activity on Deal
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('crm.deals.activities.store', $deal), [
                'activity_type' => 'note',
                'summary' => 'Debrief call on lost opportunity',
                'details' => 'Client selected competing proposal due to location preference.',
            ])
            ->assertRedirect(route('crm.deals.show', $deal));

        $dealActivity = App\Modules\Crm\Models\CrmActivity::query()
            ->where('subject_type', CrmDeal::class)
            ->where('subject_id', $deal->id)
            ->first();
        expect($dealActivity)->not->toBeNull();

        // 6. Soft Delete Deal
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('crm.deals.destroy', $deal))
            ->assertRedirect(route('crm.deals.index'));

        expect(CrmDeal::query()->find($deal->id))->toBeNull()
            ->and(CrmDeal::withTrashed()->find($deal->id))->not->toBeNull();
    });

    test('adversarial negative tests reject unauthorized actions and cross-tenant mutations on all CRM entities', function (): void {
        [$userA, $tenantA] = createCrmTestContext();

        $tenantB = Tenant::factory()->create(['isolation_mode' => 'shared']);
        $foreignAccount = CrmAccount::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'name' => 'Foreign Account',
            'code' => 'ACC-TENANTB',
            'account_type' => 'corporate',
            'status' => 'active',
        ]);
        $foreignContact = CrmContact::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'account_id' => $foreignAccount->id,
            'first_name' => 'Foreign',
            'last_name' => 'Contact',
            'email' => 'foreign@tenantb.test',
        ]);
        $foreignLead = CrmLead::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'reference_code' => 'LEAD-TENANTB',
            'source' => CrmLead::SOURCE_WALK_IN,
            'full_name' => 'Foreign Lead',
            'status' => CrmLead::STATUS_NEW,
        ]);
        $foreignDeal = CrmDeal::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'account_id' => $foreignAccount->id,
            'title' => 'Foreign Deal',
            'stage' => CrmDeal::STAGE_DISCOVERY,
            'currency' => 'GHS',
            'value_pesewas' => 50000000,
            'probability' => 20,
        ]);

        // Cross-tenant Lead access & mutations -> 404
        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->get(route('crm.leads.show', $foreignLead))
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->get(route('crm.leads.edit', $foreignLead))
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->put(route('crm.leads.update', $foreignLead), ['full_name' => 'Hacked Name', 'source' => 'walk_in'])
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->delete(route('crm.leads.destroy', $foreignLead))
            ->assertNotFound();

        // Cross-tenant Account access & mutations -> 404
        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->get(route('crm.accounts.show', $foreignAccount))
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->get(route('crm.accounts.edit', $foreignAccount))
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->put(route('crm.accounts.update', $foreignAccount), ['name' => 'Hacked Corp', 'account_type' => 'corporate', 'status' => 'active'])
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->delete(route('crm.accounts.destroy', $foreignAccount))
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->delete(route('crm.contacts.destroy', $foreignContact))
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->get(route('crm.contacts.edit', $foreignContact))
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->put(route('crm.contacts.update', $foreignContact), ['first_name' => 'Hacked Contact'])
            ->assertNotFound();

        // Cross-tenant Deal access & mutations -> 404
        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->get(route('crm.deals.show', $foreignDeal))
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->get(route('crm.deals.edit', $foreignDeal))
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->put(route('crm.deals.update', $foreignDeal), ['title' => 'Hacked Deal', 'stage' => 'discovery', 'amount_ghs' => 100, 'probability' => 10])
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->delete(route('crm.deals.destroy', $foreignDeal))
            ->assertNotFound();

        // Unpermitted user in Tenant A cannot delete or manage leads
        $unpermittedUser = User::factory()->create(['tenant_id' => $tenantA->id]);
        $unpermittedUser->tenants()->syncWithoutDetaching([$tenantA->id]);
        // Only grant crm.view, no crm.leads.manage
        $unpermittedUser->givePermissionTo('crm.view');

        $localLead = CrmLead::create([
            'tenant_id' => (string) $tenantA->id,
            'reference_code' => 'LEAD-LOCAL-01',
            'source' => CrmLead::SOURCE_WALK_IN,
            'full_name' => 'Local Lead',
            'status' => CrmLead::STATUS_NEW,
        ]);

        $this->actingAs($unpermittedUser)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->delete(route('crm.leads.destroy', $localLead))
            ->assertForbidden();

        $this->actingAs($unpermittedUser)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->put(route('crm.leads.update', $localLead), ['full_name' => 'Attempted Edit', 'source' => 'walk_in'])
            ->assertForbidden();
    });
});
