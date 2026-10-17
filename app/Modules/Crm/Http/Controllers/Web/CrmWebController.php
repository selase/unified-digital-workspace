<?php

declare(strict_types=1);

namespace App\Modules\Crm\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Crm\Models\CrmAccount;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\CrmContact;
use App\Modules\Crm\Models\CrmDeal;
use App\Modules\Crm\Models\CrmLead;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CrmWebController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('crm.view'), 403);

        $leads = CrmLead::query()
            ->with(['assignedTo:uuid,first_name,last_name,email', 'convertedAccount:id,uuid,name,code'])
            ->latest()
            ->limit(25)
            ->get();

        $accounts = CrmAccount::query()
            ->with(['contacts', 'owner:uuid,first_name,last_name,email'])
            ->withCount(['contacts', 'deals'])
            ->latest()
            ->limit(25)
            ->get();

        $deals = CrmDeal::query()
            ->with(['account:id,uuid,name,code', 'owner:uuid,first_name,last_name,email'])
            ->latest()
            ->limit(25)
            ->get();

        $activities = CrmActivity::query()
            ->with(['loggedBy:uuid,first_name,last_name,email'])
            ->latest('occurred_at')
            ->limit(15)
            ->get();

        $totalPipelinePesewas = (int) CrmDeal::query()
            ->whereNotIn('stage', ['closed_lost'])
            ->sum('value_pesewas');

        $wonPipelinePesewas = (int) CrmDeal::query()
            ->where('stage', CrmDeal::STAGE_CLOSED_WON)
            ->sum('value_pesewas');

        $metrics = [
            'accounts_total' => CrmAccount::query()->count(),
            'leads_open' => CrmLead::query()->whereIn('status', [CrmLead::STATUS_NEW, CrmLead::STATUS_CONTACTED, CrmLead::STATUS_QUALIFIED])->count(),
            'leads_walk_in' => CrmLead::query()->where('source', CrmLead::SOURCE_WALK_IN)->count(),
            'leads_converted' => CrmLead::query()->where('status', CrmLead::STATUS_CONVERTED)->count(),
            'pipeline_ghs' => number_format($totalPipelinePesewas / 100, 2),
            'won_ghs' => number_format($wonPipelinePesewas / 100, 2),
        ];

        return view('crm::index', [
            'leads' => $leads,
            'accounts' => $accounts,
            'deals' => $deals,
            'activities' => $activities,
            'metrics' => $metrics,
        ]);
    }

    public function storeLead(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.leads.manage'), 403);

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'organisation' => ['nullable', 'string', 'max:255'],
            'source' => ['required', 'string', Rule::in([
                CrmLead::SOURCE_WALK_IN,
                CrmLead::SOURCE_WEBSITE_ENQUIRY,
                CrmLead::SOURCE_PROPERTY_LISTING,
                CrmLead::SOURCE_REFERRAL,
                CrmLead::SOURCE_DIRECT,
            ])],
            'enquiry_type' => ['nullable', 'string', 'max:64'],
            'interested_listing_id' => ['nullable', 'integer'],
            'message' => ['nullable', 'string', 'max:3000'],
        ]);

        $lead = CrmLead::create([
            'reference_code' => 'LEAD-'.mb_strtoupper(Str::random(6)),
            'source' => $validated['source'],
            'enquiry_type' => $validated['enquiry_type'] ?? 'general',
            'full_name' => $validated['full_name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'organisation' => $validated['organisation'] ?? null,
            'interested_listing_id' => $validated['interested_listing_id'] ?? null,
            'message' => $validated['message'] ?? null,
            'status' => CrmLead::STATUS_NEW,
            'assigned_to_id' => (string) $user->uuid,
        ]);

        CrmActivity::create([
            'subject_type' => CrmLead::class,
            'subject_id' => $lead->id,
            'activity_type' => $lead->source === CrmLead::SOURCE_WALK_IN ? 'walk_in_visit' : 'note',
            'summary' => "Lead registered via {$lead->source}: {$lead->full_name}",
            'details' => $lead->message,
            'logged_by_id' => (string) $user->uuid,
            'occurred_at' => now(),
        ]);

        \App\Modules\WorkflowAutomation\Events\WorkflowDomainEvent::dispatch(
            (string) ($lead->tenant_id ?? app(TenantContext::class)->getTenant()?->id),
            'crm.inquiry.created',
            [
                'lead_id' => $lead->id,
                'reference_code' => $lead->reference_code,
                'full_name' => $lead->full_name,
                'source' => $lead->source,
                'status' => $lead->status,
            ]
        );

        return redirect()
            ->route('crm.index')
            ->with('status', 'success')
            ->with('message', "Lead {$lead->reference_code} captured in CRM.");
    }

    public function convertLead(Request $request, CrmLead $lead): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.convert'), 403);

        if ($lead->status === CrmLead::STATUS_CONVERTED || $lead->converted_account_id !== null) {
            return back()->withErrors(['lead' => 'This lead has already been converted into a client account.']);
        }

        $tenant = app(TenantContext::class)->getTenant();

        $nameParts = preg_split('/\s+/', mb_trim($lead->full_name), 2) ?: [$lead->full_name, 'Client'];
        $firstName = $nameParts[0] !== '' ? $nameParts[0] : 'Client';
        $lastName = $nameParts[1] ?? 'Account';

        $portalUserUuid = null;
        if ($request->boolean('provision_portal_user') && filled($lead->email) && $tenant) {
            $portalUser = User::query()->firstOrCreate(
                ['email' => $lead->email],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'password' => bcrypt(Str::random(16)),
                    'phone_no' => $lead->phone ?? '233000000000',
                    'status' => User::STATUS_ACTIVE,
                    'tenant_id' => $tenant->id,
                ]
            );
            $portalUser->forceFill(['email_verified_at' => now()])->save();
            $portalUser->tenants()->syncWithoutDetaching([$tenant->id]);
            $portalUserUuid = (string) $portalUser->uuid;
        }

        $account = CrmAccount::create([
            'name' => $lead->organisation ?: $lead->full_name,
            'code' => 'ACC-'.mb_strtoupper(Str::random(6)),
            'account_type' => filled($lead->organisation) ? 'corporate' : 'individual',
            'email' => $lead->email,
            'phone' => $lead->phone,
            'owner_id' => (string) $user->uuid,
            'portal_user_id' => $portalUserUuid,
            'status' => 'active',
        ]);

        $contact = CrmContact::create([
            'account_id' => $account->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'is_primary' => true,
            'portal_user_id' => $portalUserUuid,
        ]);

        $lead->update([
            'status' => CrmLead::STATUS_CONVERTED,
            'converted_account_id' => $account->id,
            'converted_contact_id' => $contact->id,
            'converted_at' => now(),
        ]);

        CrmActivity::create([
            'subject_type' => CrmAccount::class,
            'subject_id' => $account->id,
            'activity_type' => 'conversion',
            'summary' => "Converted Lead {$lead->reference_code} into Client Account {$account->code}",
            'details' => "Primary contact {$contact->fullName()} onboarded.",
            'logged_by_id' => (string) $user->uuid,
            'occurred_at' => now(),
        ]);

        \App\Modules\WorkflowAutomation\Events\WorkflowDomainEvent::dispatch(
            (string) ($lead->tenant_id ?? app(TenantContext::class)->getTenant()?->id),
            'crm.lead.converted',
            [
                'lead_id' => $lead->id,
                'reference_code' => $lead->reference_code,
                'account_id' => $account->id,
                'account_name' => $account->name,
                'contact_id' => $contact->id,
                'status' => $lead->status,
            ]
        );

        return redirect()
            ->route('crm.index')
            ->with('status', 'success')
            ->with('message', "Lead {$lead->reference_code} converted to Client Account.");
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.accounts.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'string', Rule::in(['corporate', 'individual', 'government', 'partner'])],
            'industry' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_first_name' => ['nullable', 'string', 'max:120'],
            'contact_last_name' => ['nullable', 'string', 'max:120'],
            'contact_job_title' => ['nullable', 'string', 'max:120'],
        ]);

        $account = CrmAccount::create([
            'name' => $validated['name'],
            'code' => 'ACC-'.mb_strtoupper(Str::random(6)),
            'account_type' => $validated['account_type'],
            'industry' => $validated['industry'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'owner_id' => (string) $user->uuid,
            'status' => 'active',
        ]);

        if (! empty($validated['contact_first_name'])) {
            CrmContact::create([
                'account_id' => $account->id,
                'first_name' => $validated['contact_first_name'],
                'last_name' => $validated['contact_last_name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'job_title' => $validated['contact_job_title'] ?? null,
                'is_primary' => true,
            ]);
        }

        return redirect()
            ->route('crm.index')
            ->with('status', 'success')
            ->with('message', "Client Account {$account->code} created.");
    }

    public function storeDeal(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.deals.manage'), 403);

        $activeTenant = app(TenantContext::class)->getTenant();
        $tenantId = $activeTenant !== null ? (string) $activeTenant->id : '';

        $validated = $request->validate([
            'account_id' => [
                'required',
                'integer',
                Rule::exists('tenant.crm_accounts', 'id')->where('tenant_id', $tenantId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'stage' => ['required', 'string', Rule::in([
                CrmDeal::STAGE_DISCOVERY,
                CrmDeal::STAGE_SITE_VISIT,
                CrmDeal::STAGE_PROPOSAL,
                CrmDeal::STAGE_NEGOTIATION,
                CrmDeal::STAGE_CLOSED_WON,
                CrmDeal::STAGE_CLOSED_AWAY,
            ])],
            'amount_ghs' => ['required', 'numeric', 'min:0'],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $deal = CrmDeal::create([
            'account_id' => (int) $validated['account_id'],
            'title' => $validated['title'],
            'stage' => $validated['stage'],
            'currency' => 'GHS',
            'value_pesewas' => (int) round(((float) $validated['amount_ghs']) * 100),
            'probability' => (int) $validated['probability'],
            'expected_close_date' => $validated['expected_close_date'] ?? null,
            'owner_id' => (string) $user->uuid,
            'notes' => $validated['notes'] ?? null,
        ]);

        CrmActivity::create([
            'subject_type' => CrmDeal::class,
            'subject_id' => $deal->id,
            'activity_type' => 'note',
            'summary' => "Deal opened: {$deal->title} ({$deal->formattedValue()})",
            'logged_by_id' => (string) $user->uuid,
            'occurred_at' => now(),
        ]);

        return redirect()
            ->route('crm.index')
            ->with('status', 'success')
            ->with('message', 'Deal added to pipeline.');
    }

    public function updateDealStage(Request $request, CrmDeal $deal): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.deals.manage'), 403);

        $validated = $request->validate([
            'stage' => ['required', 'string', Rule::in([
                CrmDeal::STAGE_DISCOVERY,
                CrmDeal::STAGE_SITE_VISIT,
                CrmDeal::STAGE_PROPOSAL,
                CrmDeal::STAGE_NEGOTIATION,
                CrmDeal::STAGE_CLOSED_WON,
                CrmDeal::STAGE_CLOSED_AWAY,
            ])],
        ]);

        $oldStage = $deal->stage;
        $deal->update([
            'stage' => $validated['stage'],
            'probability' => match ($validated['stage']) {
                CrmDeal::STAGE_CLOSED_WON => 100,
                CrmDeal::STAGE_CLOSED_AWAY => 0,
                CrmDeal::STAGE_NEGOTIATION => 75,
                CrmDeal::STAGE_PROPOSAL => 50,
                CrmDeal::STAGE_SITE_VISIT => 35,
                default => 20,
            },
        ]);

        CrmActivity::create([
            'subject_type' => CrmDeal::class,
            'subject_id' => $deal->id,
            'activity_type' => 'note',
            'summary' => "Deal '{$deal->title}' moved from {$oldStage} to {$deal->stage}",
            'logged_by_id' => (string) $user->uuid,
            'occurred_at' => now(),
        ]);

        return redirect()
            ->route('crm.index')
            ->with('status', 'success')
            ->with('message', 'Deal stage updated.');
    }
}
