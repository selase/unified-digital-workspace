<?php

declare(strict_types=1);

namespace App\Modules\Crm\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Crm\Models\CrmAccount;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\CrmContact;
use App\Modules\Crm\Models\CrmDeal;
use App\Modules\Crm\Models\CrmLead;
use App\Modules\WorkflowAutomation\Events\WorkflowDomainEvent;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CrmWebController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('crm.view'), 403);

        $tenantId = $this->resolveTenantId($request);

        $leads = CrmLead::query()
            ->where('tenant_id', $tenantId)
            ->with(['assignedTo:uuid,first_name,last_name,email', 'convertedAccount:id,uuid,name,code'])
            ->latest()
            ->limit(10)
            ->get();

        $accounts = CrmAccount::query()
            ->where('tenant_id', $tenantId)
            ->with(['contacts', 'owner:uuid,first_name,last_name,email'])
            ->withCount(['contacts', 'deals'])
            ->latest()
            ->limit(10)
            ->get();

        $deals = CrmDeal::query()
            ->where('tenant_id', $tenantId)
            ->with(['account:id,uuid,name,code', 'owner:uuid,first_name,last_name,email'])
            ->latest()
            ->limit(10)
            ->get();

        $activities = CrmActivity::query()
            ->where('tenant_id', $tenantId)
            ->with(['loggedBy:uuid,first_name,last_name,email'])
            ->latest('occurred_at')
            ->limit(15)
            ->get();

        $totalPipelinePesewas = (int) CrmDeal::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('stage', [CrmDeal::STAGE_CLOSED_LOST])
            ->sum('value_pesewas');

        $wonPipelinePesewas = (int) CrmDeal::query()
            ->where('tenant_id', $tenantId)
            ->where('stage', CrmDeal::STAGE_CLOSED_WON)
            ->sum('value_pesewas');

        $metrics = [
            'accounts_total' => CrmAccount::query()->where('tenant_id', $tenantId)->count(),
            'leads_open' => CrmLead::query()->where('tenant_id', $tenantId)->whereIn('status', [CrmLead::STATUS_NEW, CrmLead::STATUS_CONTACTED, CrmLead::STATUS_QUALIFIED])->count(),
            'leads_walk_in' => CrmLead::query()->where('tenant_id', $tenantId)->where('source', CrmLead::SOURCE_WALK_IN)->count(),
            'leads_converted' => CrmLead::query()->where('tenant_id', $tenantId)->where('status', CrmLead::STATUS_CONVERTED)->count(),
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

    public function pipeline(Request $request): View
    {
        abort_if(! $request->user()?->can('crm.view'), 403);

        $tenantId = $this->resolveTenantId($request);
        $stages = CrmDeal::STAGES;

        $deals = CrmDeal::query()
            ->where('tenant_id', $tenantId)
            ->with(['account:id,uuid,name,code', 'owner:uuid,first_name,last_name,email'])
            ->latest()
            ->get();

        $dealsByStage = [];
        $stageMetrics = [];

        foreach (array_keys($stages) as $stageKey) {
            $dealsForStage = $deals->where('stage', $stageKey)->values();
            $dealsByStage[$stageKey] = $dealsForStage;
            $stageMetrics[$stageKey] = [
                'count' => $dealsForStage->count(),
                'total_pesewas' => $dealsForStage->sum('value_pesewas'),
            ];
        }

        return view('crm::pipeline', compact('stages', 'dealsByStage', 'stageMetrics'));
    }

    // ==========================================
    // LEADS
    // ==========================================

    public function leadsIndex(Request $request): View
    {
        abort_if(! $request->user()?->can('crm.view'), 403);

        $tenantId = $this->resolveTenantId($request);
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $source = $request->string('source')->trim()->toString();

        $leads = CrmLead::query()
            ->where('tenant_id', $tenantId)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $sub) use ($search): void {
                    $sub->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('reference_code', 'like', "%{$search}%")
                        ->orWhere('organisation', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($source !== '', fn (Builder $query) => $query->where('source', $source))
            ->with(['assignedTo:uuid,first_name,last_name,email', 'convertedAccount:id,uuid,name,code'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('crm::leads.index', compact('leads', 'search', 'status', 'source'));
    }

    public function storeLead(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.leads.manage'), 403);

        $tenantId = $this->resolveTenantId($request);

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
            'tenant_id' => $tenantId,
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
            'tenant_id' => $tenantId,
            'subject_type' => CrmLead::class,
            'subject_id' => $lead->id,
            'activity_type' => $lead->source === CrmLead::SOURCE_WALK_IN ? 'walk_in_visit' : 'note',
            'summary' => "Lead registered via {$lead->source}: {$lead->full_name}",
            'details' => $lead->message,
            'logged_by_id' => (string) $user->uuid,
            'occurred_at' => now(),
        ]);

        WorkflowDomainEvent::dispatch(
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

    public function showLead(Request $request, CrmLead $lead): View
    {
        abort_if(! $request->user()?->can('crm.view'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $lead);

        $lead->load([
            'assignedTo:uuid,first_name,last_name,email',
            'convertedAccount:id,uuid,name,code',
            'convertedContact:id,uuid,first_name,last_name,email',
            'activities.loggedBy:uuid,first_name,last_name,email',
        ]);

        $users = User::query()->orderBy('first_name')->limit(50)->get(['uuid', 'first_name', 'last_name', 'email']);

        return view('crm::leads.show', compact('lead', 'users'));
    }

    public function editLead(Request $request, CrmLead $lead): View
    {
        abort_if(! $request->user()?->can('crm.leads.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $lead);

        $users = User::query()->orderBy('first_name')->limit(50)->get(['uuid', 'first_name', 'last_name', 'email']);

        return view('crm::leads.edit', compact('lead', 'users'));
    }

    public function updateLead(Request $request, CrmLead $lead): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.leads.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $lead);

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
            'message' => ['nullable', 'string', 'max:3000'],
            'assigned_to_id' => ['nullable', 'string'],
        ]);

        $lead->update($validated);

        CrmActivity::create([
            'tenant_id' => $tenantId,
            'subject_type' => CrmLead::class,
            'subject_id' => $lead->id,
            'activity_type' => 'note',
            'summary' => "Lead {$lead->reference_code} details updated",
            'logged_by_id' => (string) $user->uuid,
            'occurred_at' => now(),
        ]);

        return redirect()
            ->route('crm.leads.show', $lead)
            ->with('status', 'success')
            ->with('message', "Lead {$lead->reference_code} updated successfully.");
    }

    public function destroyLead(Request $request, CrmLead $lead): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.leads.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $lead);

        $reference = $lead->reference_code;
        $lead->delete();

        return redirect()
            ->route('crm.leads.index')
            ->with('status', 'success')
            ->with('message', "Lead {$reference} soft-deleted.");
    }

    public function updateLeadStatus(Request $request, CrmLead $lead): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.leads.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $lead);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([
                CrmLead::STATUS_NEW,
                CrmLead::STATUS_CONTACTED,
                CrmLead::STATUS_QUALIFIED,
                CrmLead::STATUS_LOST,
            ])],
        ]);

        $oldStatus = $lead->status;
        $lead->update(['status' => $validated['status']]);

        CrmActivity::create([
            'tenant_id' => $tenantId,
            'subject_type' => CrmLead::class,
            'subject_id' => $lead->id,
            'activity_type' => 'status_change',
            'summary' => "Lead {$lead->reference_code} status moved from {$oldStatus} to {$lead->status}",
            'logged_by_id' => (string) $user->uuid,
            'occurred_at' => now(),
        ]);

        return redirect()
            ->route('crm.leads.show', $lead)
            ->with('status', 'success')
            ->with('message', "Lead status changed to {$lead->status}.");
    }

    public function storeLeadActivity(Request $request, CrmLead $lead): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.leads.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $lead);

        $validated = $request->validate([
            'activity_type' => ['required', 'string', Rule::in(['call', 'note', 'meeting', 'email', 'walk_in_visit'])],
            'summary' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:3000'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        CrmActivity::create([
            'tenant_id' => $tenantId,
            'subject_type' => CrmLead::class,
            'subject_id' => $lead->id,
            'activity_type' => $validated['activity_type'],
            'summary' => $validated['summary'],
            'details' => $validated['details'] ?? null,
            'logged_by_id' => (string) $user->uuid,
            'occurred_at' => ! empty($validated['occurred_at']) ? $validated['occurred_at'] : now(),
        ]);

        return redirect()
            ->route('crm.leads.show', $lead)
            ->with('status', 'success')
            ->with('message', 'Activity logged on lead.');
    }

    public function convertLead(Request $request, CrmLead $lead): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.convert'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $lead);

        if ($lead->status === CrmLead::STATUS_CONVERTED || $lead->converted_account_id !== null) {
            return back()->withErrors(['lead' => 'This lead has already been converted into a client account.']);
        }

        $tenant = app(TenantContext::class)->getTenant();

        $nameParts = preg_split('/\s+/', mb_trim($lead->full_name), 2) ?: [$lead->full_name, 'Client'];
        $firstName = $nameParts[0] !== '' ? $nameParts[0] : 'Client';
        $lastName = $nameParts[1] ?? 'Account';

        [$account, $contact] = DB::transaction(function () use ($request, $lead, $tenantId, $user, $tenant, $firstName, $lastName): array {
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
                'tenant_id' => $tenantId,
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
                'tenant_id' => $tenantId,
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
                'tenant_id' => $tenantId,
                'subject_type' => CrmAccount::class,
                'subject_id' => $account->id,
                'activity_type' => 'conversion',
                'summary' => "Converted Lead {$lead->reference_code} into Client Account {$account->code}",
                'details' => "Primary contact {$contact->fullName()} onboarded.",
                'logged_by_id' => (string) $user->uuid,
                'occurred_at' => now(),
            ]);

            return [$account, $contact];
        });

        WorkflowDomainEvent::dispatch(
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

    // ==========================================
    // ACCOUNTS
    // ==========================================

    public function accountsIndex(Request $request): View
    {
        abort_if(! $request->user()?->can('crm.view'), 403);

        $tenantId = $this->resolveTenantId($request);
        $search = $request->string('search')->trim()->toString();
        $accountType = $request->string('account_type')->trim()->toString();
        $status = $request->string('status')->trim()->toString();

        $accounts = CrmAccount::query()
            ->where('tenant_id', $tenantId)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($accountType !== '', fn (Builder $query) => $query->where('account_type', $accountType))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->with(['contacts', 'owner:uuid,first_name,last_name,email'])
            ->withCount(['contacts', 'deals'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('crm::accounts.index', compact('accounts', 'search', 'accountType', 'status'));
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.accounts.manage'), 403);

        $tenantId = $this->resolveTenantId($request);

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

        $account = DB::transaction(function () use ($validated, $tenantId, $user): CrmAccount {
            $account = CrmAccount::create([
                'tenant_id' => $tenantId,
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
                    'tenant_id' => $tenantId,
                    'account_id' => $account->id,
                    'first_name' => $validated['contact_first_name'],
                    'last_name' => $validated['contact_last_name'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'job_title' => $validated['contact_job_title'] ?? null,
                    'is_primary' => true,
                ]);
            }

            return $account;
        });

        return redirect()
            ->route('crm.index')
            ->with('status', 'success')
            ->with('message', "Client Account {$account->code} created.");
    }

    public function showAccount(Request $request, CrmAccount $account): View
    {
        abort_if(! $request->user()?->can('crm.view'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $account);

        $account->load([
            'contacts',
            'deals' => fn ($q) => $q->latest(),
            'activities.loggedBy:uuid,first_name,last_name,email',
            'owner:uuid,first_name,last_name,email',
        ]);

        return view('crm::accounts.show', compact('account'));
    }

    public function editAccount(Request $request, CrmAccount $account): View
    {
        abort_if(! $request->user()?->can('crm.accounts.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $account);

        return view('crm::accounts.edit', compact('account'));
    }

    public function updateAccount(Request $request, CrmAccount $account): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.accounts.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $account);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'string', Rule::in(['corporate', 'individual', 'government', 'partner'])],
            'industry' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        $account->update($validated);

        CrmActivity::create([
            'tenant_id' => $tenantId,
            'subject_type' => CrmAccount::class,
            'subject_id' => $account->id,
            'activity_type' => 'note',
            'summary' => "Account {$account->code} profile updated",
            'logged_by_id' => (string) $user->uuid,
            'occurred_at' => now(),
        ]);

        return redirect()
            ->route('crm.accounts.show', $account)
            ->with('status', 'success')
            ->with('message', "Account {$account->code} updated successfully.");
    }

    public function destroyAccount(Request $request, CrmAccount $account): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.accounts.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $account);

        $code = $account->code;
        $account->delete();

        return redirect()
            ->route('crm.accounts.index')
            ->with('status', 'success')
            ->with('message', "Account {$code} soft-deleted.");
    }

    public function storeAccountContact(Request $request, CrmAccount $account): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.accounts.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $account);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $isPrimary = (bool) ($validated['is_primary'] ?? false);

        DB::transaction(function () use ($account, $validated, $tenantId, $user, $isPrimary): void {
            if ($isPrimary) {
                CrmContact::where('account_id', $account->id)->update(['is_primary' => false]);
            }

            $contact = CrmContact::create([
                'tenant_id' => $tenantId,
                'account_id' => $account->id,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'job_title' => $validated['job_title'] ?? null,
                'is_primary' => $isPrimary,
            ]);

            CrmActivity::create([
                'tenant_id' => $tenantId,
                'subject_type' => CrmAccount::class,
                'subject_id' => $account->id,
                'activity_type' => 'note',
                'summary' => "New contact added: {$contact->fullName()} ({$contact->job_title})",
                'logged_by_id' => (string) $user->uuid,
                'occurred_at' => now(),
            ]);
        });

        return redirect()
            ->route('crm.accounts.show', $account)
            ->with('status', 'success')
            ->with('message', 'Contact added to account.');
    }

    public function editContact(Request $request, CrmContact $contact): View
    {
        abort_if(! $request->user()?->can('crm.accounts.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $contact);

        $contact->load('account');

        return view('crm::contacts.edit', [
            'contact' => $contact,
            'account' => $contact->account,
        ]);
    }

    public function updateContact(Request $request, CrmContact $contact): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.accounts.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $contact);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $isPrimary = (bool) ($validated['is_primary'] ?? false);

        DB::transaction(function () use ($contact, $validated, $tenantId, $user, $isPrimary): void {
            if ($isPrimary && ! $contact->is_primary && $contact->account_id) {
                CrmContact::where('account_id', $contact->account_id)
                    ->where('id', '!=', $contact->id)
                    ->update(['is_primary' => false]);
            }

            $contact->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'job_title' => $validated['job_title'] ?? null,
                'is_primary' => $isPrimary,
            ]);

            if ($contact->account_id) {
                CrmActivity::create([
                    'tenant_id' => $tenantId,
                    'subject_type' => CrmAccount::class,
                    'subject_id' => $contact->account_id,
                    'activity_type' => 'note',
                    'summary' => "Contact details updated: {$contact->fullName()} ({$contact->job_title})",
                    'logged_by_id' => (string) $user->uuid,
                    'occurred_at' => now(),
                ]);
            }
        });

        $redirectTarget = $contact->account ? route('crm.accounts.show', $contact->account) : route('crm.accounts.index');

        return redirect($redirectTarget)
            ->with('status', 'success')
            ->with('message', "Contact {$contact->fullName()} updated successfully.");
    }

    public function destroyContact(Request $request, CrmContact $contact): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.accounts.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $contact);

        $account = $contact->account;
        $name = $contact->fullName();
        $contact->delete();

        return redirect()
            ->route('crm.accounts.show', $account)
            ->with('status', 'success')
            ->with('message', "Contact {$name} removed.");
    }

    public function storeAccountActivity(Request $request, CrmAccount $account): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.accounts.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $account);

        $validated = $request->validate([
            'activity_type' => ['required', 'string', Rule::in(['call', 'note', 'meeting', 'email'])],
            'summary' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:3000'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        CrmActivity::create([
            'tenant_id' => $tenantId,
            'subject_type' => CrmAccount::class,
            'subject_id' => $account->id,
            'activity_type' => $validated['activity_type'],
            'summary' => $validated['summary'],
            'details' => $validated['details'] ?? null,
            'logged_by_id' => (string) $user->uuid,
            'occurred_at' => ! empty($validated['occurred_at']) ? $validated['occurred_at'] : now(),
        ]);

        return redirect()
            ->route('crm.accounts.show', $account)
            ->with('status', 'success')
            ->with('message', 'Activity logged on account.');
    }

    // ==========================================
    // DEALS
    // ==========================================

    public function dealsIndex(Request $request): View
    {
        abort_if(! $request->user()?->can('crm.view'), 403);

        $tenantId = $this->resolveTenantId($request);
        $search = $request->string('search')->trim()->toString();
        $stage = $request->string('stage')->trim()->toString();

        $deals = CrmDeal::query()
            ->where('tenant_id', $tenantId)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $sub) use ($search): void {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhereHas('account', fn (Builder $a) => $a->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($stage !== '', fn (Builder $query) => $query->where('stage', $stage))
            ->with(['account:id,uuid,name,code', 'owner:uuid,first_name,last_name,email'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stages = CrmDeal::STAGES;

        return view('crm::deals.index', compact('deals', 'search', 'stage', 'stages'));
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
                CrmDeal::STAGE_CLOSED_LOST,
            ])],
            'amount_ghs' => ['required', 'numeric', 'min:0'],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $deal = CrmDeal::create([
            'tenant_id' => $tenantId,
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
            'tenant_id' => $tenantId,
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

    public function showDeal(Request $request, CrmDeal $deal): View
    {
        abort_if(! $request->user()?->can('crm.view'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $deal);

        $deal->load([
            'account',
            'lead',
            'owner:uuid,first_name,last_name,email',
            'activities.loggedBy:uuid,first_name,last_name,email',
        ]);

        $stages = CrmDeal::STAGES;

        return view('crm::deals.show', compact('deal', 'stages'));
    }

    public function editDeal(Request $request, CrmDeal $deal): View
    {
        abort_if(! $request->user()?->can('crm.deals.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $deal);

        $accounts = CrmAccount::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']);
        $stages = CrmDeal::STAGES;

        return view('crm::deals.edit', compact('deal', 'accounts', 'stages'));
    }

    public function updateDeal(Request $request, CrmDeal $deal): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.deals.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $deal);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'stage' => ['required', 'string', Rule::in(array_keys(CrmDeal::STAGES))],
            'amount_ghs' => ['required', 'numeric', 'min:0'],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $deal->update([
            'title' => $validated['title'],
            'stage' => $validated['stage'],
            'value_pesewas' => (int) round(((float) $validated['amount_ghs']) * 100),
            'probability' => (int) $validated['probability'],
            'expected_close_date' => $validated['expected_close_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        CrmActivity::create([
            'tenant_id' => $tenantId,
            'subject_type' => CrmDeal::class,
            'subject_id' => $deal->id,
            'activity_type' => 'note',
            'summary' => "Deal {$deal->title} details updated",
            'logged_by_id' => (string) $user->uuid,
            'occurred_at' => now(),
        ]);

        return redirect()
            ->route('crm.deals.show', $deal)
            ->with('status', 'success')
            ->with('message', 'Deal updated successfully.');
    }

    public function destroyDeal(Request $request, CrmDeal $deal): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.deals.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $deal);

        $title = $deal->title;
        $deal->delete();

        return redirect()
            ->route('crm.deals.index')
            ->with('status', 'success')
            ->with('message', "Deal {$title} soft-deleted.");
    }

    public function updateDealStage(Request $request, CrmDeal $deal): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.deals.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $deal);

        $validated = $request->validate([
            'stage' => ['required', 'string', Rule::in([
                CrmDeal::STAGE_DISCOVERY,
                CrmDeal::STAGE_SITE_VISIT,
                CrmDeal::STAGE_PROPOSAL,
                CrmDeal::STAGE_NEGOTIATION,
                CrmDeal::STAGE_CLOSED_WON,
                CrmDeal::STAGE_CLOSED_LOST,
            ])],
        ]);

        $oldStage = $deal->stage;
        $deal->update([
            'stage' => $validated['stage'],
            'probability' => match ($validated['stage']) {
                CrmDeal::STAGE_CLOSED_WON => 100,
                CrmDeal::STAGE_CLOSED_LOST => 0,
                CrmDeal::STAGE_NEGOTIATION => 75,
                CrmDeal::STAGE_PROPOSAL => 50,
                CrmDeal::STAGE_SITE_VISIT => 35,
                default => 20,
            },
        ]);

        CrmActivity::create([
            'tenant_id' => $tenantId,
            'subject_type' => CrmDeal::class,
            'subject_id' => $deal->id,
            'activity_type' => 'note',
            'summary' => "Deal '{$deal->title}' moved from {$oldStage} to {$deal->stage}",
            'logged_by_id' => (string) $user->uuid,
            'occurred_at' => now(),
        ]);

        return redirect()
            ->back(fallback: route('crm.index'))
            ->with('status', 'success')
            ->with('message', 'Deal stage updated.');
    }

    public function storeDealActivity(Request $request, CrmDeal $deal): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('crm.deals.manage'), 403);

        $tenantId = $this->resolveTenantId($request);
        $this->authorizeTenant($tenantId, $deal);

        $validated = $request->validate([
            'activity_type' => ['required', 'string', Rule::in(['call', 'note', 'meeting', 'email'])],
            'summary' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:3000'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        CrmActivity::create([
            'tenant_id' => $tenantId,
            'subject_type' => CrmDeal::class,
            'subject_id' => $deal->id,
            'activity_type' => $validated['activity_type'],
            'summary' => $validated['summary'],
            'details' => $validated['details'] ?? null,
            'logged_by_id' => (string) $user->uuid,
            'occurred_at' => ! empty($validated['occurred_at']) ? $validated['occurred_at'] : now(),
        ]);

        return redirect()
            ->route('crm.deals.show', $deal)
            ->with('status', 'success')
            ->with('message', 'Activity logged on deal.');
    }

    private function resolveTenantId(Request $request): string
    {
        $tenant = $request->attributes->get('tenant');
        if ($tenant instanceof Tenant) {
            return (string) $tenant->id;
        }

        $activeTenant = app(TenantContext::class)->getTenant();
        if ($activeTenant !== null) {
            return (string) $activeTenant->id;
        }

        $sessionTenantId = $request->session()->get('active_tenant_id');
        if (is_string($sessionTenantId) && $sessionTenantId !== '') {
            return $sessionTenantId;
        }

        $user = $request->user();
        if ($user !== null && ! empty($user->tenant_id)) {
            return (string) $user->tenant_id;
        }

        return '';
    }

    private function authorizeTenant(string $tenantId, Model $model): void
    {
        /** @var string|null $modelTenantId */
        $modelTenantId = $model->getAttribute('tenant_id');
        if ($tenantId !== '' && $modelTenantId !== null && $modelTenantId !== $tenantId) {
            abort(404, 'Record not found in this organization.');
        }
    }
}
