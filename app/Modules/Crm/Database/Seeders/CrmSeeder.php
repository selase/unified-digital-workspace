<?php

declare(strict_types=1);

namespace App\Modules\Crm\Database\Seeders;

use App\Models\User;
use App\Modules\Crm\Models\CrmAccount;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\CrmContact;
use App\Modules\Crm\Models\CrmDeal;
use App\Modules\Crm\Models\CrmLead;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

final class CrmSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->bound(TenantContext::class)) {
            return;
        }

        $tenant = app(TenantContext::class)->getTenant();

        if (! $tenant) {
            return;
        }

        $tenantConnection = config('database.default_tenant_connection', 'tenant');

        if (! Schema::connection($tenantConnection)->hasTable('crm_accounts')) {
            return;
        }

        $owner = User::query()
            ->where('tenant_id', $tenant->id)
            ->first() ?? User::query()->first();

        $ownerUuid = $owner ? (string) $owner->uuid : null;
        $tenantId = (string) $tenant->id;

        // 1. Client Accounts
        $apexAccount = CrmAccount::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'code' => 'ACC-APEX01',
        ], [
            'name' => 'Apex Healthcare Systems Ltd',
            'account_type' => 'corporate',
            'industry' => 'Healthcare & Diagnostics',
            'email' => 'partnerships@apexhealthcare.test',
            'phone' => '+233302000001',
            'address' => '14 Independence Avenue, Ridge, Accra',
            'owner_id' => $ownerUuid,
            'status' => 'active',
            'metadata' => ['tier' => 'enterprise', 'annual_revenue_bracket' => 'GHS 10M+'],
        ]);

        $mensahAccount = CrmAccount::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'code' => 'ACC-DRMEN1',
        ], [
            'name' => 'Dr. Ama Mensah',
            'account_type' => 'individual',
            'industry' => 'Private Practice / Real Estate Investor',
            'email' => 'dr.mensah@apexhealthcare.test',
            'phone' => '+233244111222',
            'address' => 'Plot 8B, Cantonments, Accra',
            'owner_id' => $ownerUuid,
            'status' => 'active',
            'metadata' => ['kyc_status' => 'verified'],
        ]);

        $logisticsAccount = CrmAccount::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'code' => 'ACC-GLOB01',
        ], [
            'name' => 'Global Logistics & Haulage Hub',
            'account_type' => 'corporate',
            'industry' => 'Transport & Supply Chain',
            'email' => 'ops@globallogistics.test',
            'phone' => '+233302555666',
            'address' => 'Tema Heavy Industrial Area',
            'owner_id' => $ownerUuid,
            'status' => 'active',
            'metadata' => ['fleet_size' => 45],
        ]);

        // 2. Contacts
        CrmContact::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'account_id' => $apexAccount->id,
            'email' => 'director@apexhealthcare.test',
        ], [
            'first_name' => 'Kofi',
            'last_name' => 'Boateng',
            'phone' => '+233201112233',
            'job_title' => 'Managing Director',
            'is_primary' => true,
        ]);

        CrmContact::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'account_id' => $apexAccount->id,
            'email' => 'finance@apexhealthcare.test',
        ], [
            'first_name' => 'Esi',
            'last_name' => 'Quaye',
            'phone' => '+233201112244',
            'job_title' => 'Chief Financial Officer',
            'is_primary' => false,
        ]);

        $mensahContact = CrmContact::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'account_id' => $mensahAccount->id,
            'email' => 'dr.mensah@apexhealthcare.test',
        ], [
            'first_name' => 'Ama',
            'last_name' => 'Mensah',
            'phone' => '+233244111222',
            'job_title' => 'Principal Consultant',
            'is_primary' => true,
        ]);

        // 3. Leads (Various statuses & sources)
        $leadWalkIn = CrmLead::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'reference_code' => 'LEAD-SEEDED-01',
        ], [
            'source' => CrmLead::SOURCE_WALK_IN,
            'enquiry_type' => 'commercial_acquisition',
            'full_name' => 'Nana Kwabena Osei',
            'email' => 'nana.osei@oseicapital.test',
            'phone' => '+233243999888',
            'organisation' => 'Osei Capital Partners',
            'message' => 'Walked into head office inquiring about 3-acre commercial plot near Airport City.',
            'status' => CrmLead::STATUS_NEW,
            'assigned_to_id' => $ownerUuid,
        ]);

        $leadWebsite = CrmLead::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'reference_code' => 'LEAD-SEEDED-02',
        ], [
            'source' => CrmLead::SOURCE_WEBSITE_ENQUIRY,
            'enquiry_type' => 'partnership',
            'full_name' => 'Abena Serwaa Darko',
            'email' => 'abena.darko@africagrowth.test',
            'phone' => '+233277333444',
            'organisation' => 'Africa Growth Advisory',
            'message' => 'Submitted online partnership inquiry for institutional workspace leasing.',
            'status' => CrmLead::STATUS_CONTACTED,
            'assigned_to_id' => $ownerUuid,
        ]);

        CrmLead::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'reference_code' => 'LEAD-SEEDED-03',
        ], [
            'source' => CrmLead::SOURCE_PROPERTY_LISTING,
            'enquiry_type' => 'property_viewing',
            'full_name' => 'Yaw Frimpong',
            'email' => 'y.frimpong@frimponginvest.test',
            'phone' => '+233208555111',
            'organisation' => 'Frimpong Holdings',
            'message' => 'Requested site inspection for Cantonments development plot.',
            'status' => CrmLead::STATUS_QUALIFIED,
            'assigned_to_id' => $ownerUuid,
        ]);

        $leadConverted = CrmLead::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'reference_code' => 'LEAD-SEEDED-04',
        ], [
            'source' => CrmLead::SOURCE_DIRECT,
            'enquiry_type' => 'private_practice',
            'full_name' => 'Dr. Ama Mensah',
            'email' => 'dr.mensah@apexhealthcare.test',
            'phone' => '+233244111222',
            'organisation' => 'Dr. Ama Mensah Clinic',
            'message' => 'Specialist clinic establishment inquiry converted to client account.',
            'status' => CrmLead::STATUS_CONVERTED,
            'assigned_to_id' => $ownerUuid,
            'converted_account_id' => $mensahAccount->id,
            'converted_contact_id' => $mensahContact->id,
            'converted_at' => now()->subDays(5),
        ]);

        CrmLead::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'reference_code' => 'LEAD-SEEDED-05',
        ], [
            'source' => CrmLead::SOURCE_REFERRAL,
            'enquiry_type' => 'retail',
            'full_name' => 'Kweku Adjei',
            'email' => 'kweku@adjeiventures.test',
            'phone' => '+233249111000',
            'organisation' => 'Adjei Ventures',
            'message' => 'Budget constraints; prospect postponed expansion project to next financial year.',
            'status' => CrmLead::STATUS_LOST,
            'assigned_to_id' => $ownerUuid,
        ]);

        // 4. Deals (Full pipeline stages)
        CrmDeal::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'account_id' => $apexAccount->id,
            'title' => 'Diagnostic Center Phase 1 Expansion',
        ], [
            'lead_id' => $leadWebsite->id,
            'stage' => CrmDeal::STAGE_DISCOVERY,
            'currency' => 'GHS',
            'value_pesewas' => 120_000_000, // GHS 1,200,000
            'probability' => 25,
            'expected_close_date' => now()->addMonths(3),
            'owner_id' => $ownerUuid,
            'notes' => 'Initial scoping discussions for modern laboratory facility.',
        ]);

        CrmDeal::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'account_id' => $apexAccount->id,
            'title' => 'Ridge Medical Annex Site Visit',
        ], [
            'stage' => CrmDeal::STAGE_SITE_VISIT,
            'currency' => 'GHS',
            'value_pesewas' => 45_000_000, // GHS 450,000
            'probability' => 35,
            'expected_close_date' => now()->addMonths(2),
            'owner_id' => $ownerUuid,
            'notes' => 'On-site walk-through scheduled with Apex Facilities director.',
        ]);

        CrmDeal::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'account_id' => $logisticsAccount->id,
            'title' => 'Warehouse Facility Long-Term Lease',
        ], [
            'stage' => CrmDeal::STAGE_PROPOSAL,
            'currency' => 'GHS',
            'value_pesewas' => 85_000_000, // GHS 850,000
            'probability' => 50,
            'expected_close_date' => now()->addMonth(),
            'owner_id' => $ownerUuid,
            'notes' => 'Draft commercial lease agreement under review by tenant legal counsel.',
        ]);

        CrmDeal::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'account_id' => $mensahAccount->id,
            'title' => 'Cantonments Specialist Suite Acquisition',
        ], [
            'lead_id' => $leadConverted->id,
            'stage' => CrmDeal::STAGE_NEGOTIATION,
            'currency' => 'GHS',
            'value_pesewas' => 60_000_000, // GHS 600,000
            'probability' => 75,
            'expected_close_date' => now()->addWeeks(2),
            'owner_id' => $ownerUuid,
            'notes' => 'Final payment milestone terms negotiated.',
        ]);

        CrmDeal::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'account_id' => $apexAccount->id,
            'title' => 'Hospital Administration Center Fitted Out',
        ], [
            'stage' => CrmDeal::STAGE_CLOSED_WON,
            'currency' => 'GHS',
            'value_pesewas' => 250_000_000, // GHS 2,500,000
            'probability' => 100,
            'expected_close_date' => now()->subDays(10),
            'owner_id' => $ownerUuid,
            'notes' => 'Contract executed and advance payment received.',
        ]);

        CrmDeal::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'account_id' => $logisticsAccount->id,
            'title' => 'Satellite Depots Tender 2026',
        ], [
            'stage' => CrmDeal::STAGE_CLOSED_LOST,
            'currency' => 'GHS',
            'value_pesewas' => 30_000_000, // GHS 300,000
            'probability' => 0,
            'expected_close_date' => now()->subMonth(),
            'owner_id' => $ownerUuid,
            'notes' => 'Lost to competitor due to zoning preference.',
        ]);

        // 5. Activity Trails
        CrmActivity::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'subject_type' => CrmLead::class,
            'subject_id' => $leadWalkIn->id,
            'summary' => 'Office walk-in reception logged',
        ], [
            'activity_type' => 'walk_in_visit',
            'details' => 'Client walked into front desk asking about Airport City listings.',
            'logged_by_id' => $ownerUuid,
            'occurred_at' => now()->subHours(6),
        ]);

        CrmActivity::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'subject_type' => CrmLead::class,
            'subject_id' => $leadWebsite->id,
            'summary' => 'Introductory call placed to prospect',
        ], [
            'activity_type' => 'call',
            'details' => 'Spoke with Abena Darko; shared brochure and requirements questionnaire.',
            'logged_by_id' => $ownerUuid,
            'occurred_at' => now()->subDays(2),
        ]);

        CrmActivity::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'subject_type' => CrmAccount::class,
            'subject_id' => $apexAccount->id,
            'summary' => 'Quarterly partnership review meeting',
        ], [
            'activity_type' => 'meeting',
            'details' => 'Reviewed commercial tenancy performance and future facility requirements.',
            'logged_by_id' => $ownerUuid,
            'occurred_at' => now()->subDays(4),
        ]);
    }
}
