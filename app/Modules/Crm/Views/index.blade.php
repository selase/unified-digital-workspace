@extends('layouts.metronic.app')

@section('title', 'CRM & Client Pipeline Hub')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">CRM & Client Pipeline Hub</h1>
            <p class="text-xs text-muted-foreground">Unified management of walk-in & online inquiries, institutional & individual client accounts, deal stages, and one-click client portal onboarding.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('crm.pipeline') }}" class="kt-btn kt-btn-sm kt-btn-primary">Kanban Pipeline</a>
            <a href="{{ route('crm.leads.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Leads Directory</a>
            <a href="{{ route('crm.accounts.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Client Accounts</a>
            <a href="{{ route('crm.deals.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Deals Directory</a>
        </div>
    </div>

    {{-- Pipeline KPI Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="kt-card p-5">
            <span class="text-xs uppercase font-semibold tracking-wider text-muted-foreground">Active Client Accounts</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $metrics['accounts_total'] }}</span>
                <span class="kt-badge kt-badge-sm kt-badge-primary">{{ $metrics['leads_converted'] }} converted leads</span>
            </div>
        </div>
        <div class="kt-card p-5">
            <span class="text-xs uppercase font-semibold tracking-wider text-muted-foreground">Open Inquiries & Leads</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $metrics['leads_open'] }}</span>
                <span class="kt-badge kt-badge-sm kt-badge-outline">{{ $metrics['leads_walk_in'] }} walk-ins</span>
            </div>
        </div>
        <div class="kt-card p-5">
            <span class="text-xs uppercase font-semibold tracking-wider text-muted-foreground">Active Pipeline Value</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-primary">GHS {{ $metrics['pipeline_ghs'] }}</span>
                <span class="text-xs text-muted-foreground">Open stages</span>
            </div>
        </div>
        <div class="kt-card p-5">
            <span class="text-xs uppercase font-semibold tracking-wider text-muted-foreground">Closed Won Revenue</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-success">GHS {{ $metrics['won_ghs'] }}</span>
                <span class="text-xs text-muted-foreground">Settled / Contracted</span>
            </div>
        </div>
    </div>

    {{-- Intake Forms Row: Walk-In / Online Lead Capture + Account & Deal Creation --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- 1. Capture Walk-In / Website / Listing Lead --}}
        @can('crm.leads.manage')
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title text-sm font-semibold">Log Walk-In or Online Inquiry</h3>
            </div>
            <div class="kt-card-body p-5">
                <form action="{{ route('crm.leads.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="kt-form-label text-xs">Full Name *</label>
                        <input type="text" name="full_name" class="kt-input" placeholder="e.g. Kwesi Appiah" required>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="kt-form-label text-xs">Source *</label>
                            <select name="source" class="kt-select" required>
                                <option value="walk_in">Office Walk-In</option>
                                <option value="property_listing">Property Listing Inquiry</option>
                                <option value="website_enquiry">Website Form</option>
                                <option value="referral">Partner Referral</option>
                                <option value="direct">Direct Outreach</option>
                            </select>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Phone</label>
                            <input type="text" name="phone" class="kt-input" placeholder="+233...">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="kt-form-label text-xs">Email</label>
                            <input type="email" name="email" class="kt-input" placeholder="client@example.com">
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Organisation (Optional)</label>
                            <input type="text" name="organisation" class="kt-input" placeholder="Company / Family Trust">
                        </div>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Inquiry Notes / Plot Interest</label>
                        <textarea name="message" rows="2" class="kt-textarea" placeholder="Visited office regarding plot purchase or service engagement..."></textarea>
                    </div>
                    <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">Capture Lead</button>
                </form>
            </div>
        </div>
        @endcan

        {{-- 2. Register Direct Client Account --}}
        @can('crm.accounts.manage')
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title text-sm font-semibold">Register Client Account</h3>
            </div>
            <div class="kt-card-body p-5">
                <form action="{{ route('crm.accounts.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="kt-form-label text-xs">Account Name *</label>
                            <input type="text" name="name" class="kt-input" placeholder="e.g. Goldfields Estates Ltd" required>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Account Type *</label>
                            <select name="account_type" class="kt-select" required>
                                <option value="corporate">Corporate</option>
                                <option value="individual">Individual Buyer</option>
                                <option value="government">Government / Agency</option>
                                <option value="partner">Strategic Partner</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="kt-form-label text-xs">Email</label>
                            <input type="email" name="email" class="kt-input" placeholder="office@client.com">
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Phone</label>
                            <input type="text" name="phone" class="kt-input" placeholder="+233...">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="kt-form-label text-xs">Primary Contact First Name</label>
                            <input type="text" name="contact_first_name" class="kt-input" placeholder="First Name">
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Primary Contact Last Name</label>
                            <input type="text" name="contact_last_name" class="kt-input" placeholder="Last Name">
                        </div>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Address / Location</label>
                        <input type="text" name="address" class="kt-input" placeholder="Airport Residential Area, Accra">
                    </div>
                    <button type="submit" class="kt-btn kt-btn-outline w-full justify-center">Create Client Account</button>
                </form>
            </div>
        </div>
        @endcan

        {{-- 3. Open Commercial / Property Deal --}}
        @can('crm.deals.manage')
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title text-sm font-semibold">Create Commercial / Land Deal</h3>
            </div>
            <div class="kt-card-body p-5">
                <form action="{{ route('crm.deals.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="kt-form-label text-xs">Client Account *</label>
                        <select name="account_id" class="kt-select" required>
                            <option value="">Select Account...</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Deal Title *</label>
                        <input type="text" name="title" class="kt-input" placeholder="e.g. 2-Acre Serviced Plot Acquisition" required>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="kt-form-label text-xs">Value (GHS) *</label>
                            <input type="number" step="0.01" min="0" name="amount_ghs" class="kt-input" placeholder="250000" required>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Stage *</label>
                            <select name="stage" class="kt-select" required>
                                <option value="discovery">Discovery</option>
                                <option value="site_visit">Site Visit</option>
                                <option value="proposal">Proposal</option>
                                <option value="negotiation">Negotiation</option>
                                <option value="closed_won">Closed Won</option>
                            </select>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Win % *</label>
                            <input type="number" min="0" max="100" name="probability" value="50" class="kt-input" required>
                        </div>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Expected Close Date</label>
                        <input type="date" name="expected_close_date" class="kt-input">
                    </div>
                    <button type="submit" class="kt-btn kt-btn-outline w-full justify-center">Add Deal to Pipeline</button>
                </form>
            </div>
        </div>
        @endcan
    </div>

    {{-- Leads & Walk-In Inquiries Registry --}}
    <div class="kt-card">
        <div class="kt-card-header flex items-center justify-between">
            <div>
                <h3 class="kt-card-title">Recent Walk-In & Online Inquiries</h3>
                <p class="text-xs text-muted-foreground">Convert qualified prospects into Client Accounts with optional Portal Login provisioning.</p>
            </div>
            <a href="{{ route('crm.leads.index') }}" class="kt-btn kt-btn-xs kt-btn-outline">View All Leads</a>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Prospect</th>
                        <th>Source</th>
                        <th>Inquiry Summary</th>
                        <th>Status</th>
                        <th>Captured</th>
                        <th class="text-end">Onboarding Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leads as $lead)
                        <tr>
                            <td class="font-mono text-xs font-semibold">
                                <a href="{{ route('crm.leads.show', $lead) }}" class="text-primary hover:underline">
                                    {{ $lead->reference_code }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('crm.leads.show', $lead) }}" class="font-semibold text-foreground hover:text-primary hover:underline">
                                    {{ $lead->full_name }}
                                </a>
                                <div class="text-xs text-muted-foreground">{{ $lead->email ?? $lead->phone ?? 'No contact' }}</div>
                            </td>
                            <td>
                                <span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ str_replace('_', ' ', $lead->source) }}</span>
                            </td>
                            <td class="max-w-[280px] truncate text-xs text-secondary-foreground">{{ $lead->message ?? '—' }}</td>
                            <td>
                                @if($lead->status === 'converted')
                                    <span class="kt-badge kt-badge-xs kt-badge-success">Converted ({{ $lead->convertedAccount?->code }})</span>
                                @else
                                    <span class="kt-badge kt-badge-xs kt-badge-warning uppercase">{{ $lead->status }}</span>
                                @endif
                            </td>
                            <td class="text-xs text-muted-foreground">{{ $lead->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                @if($lead->status !== 'converted' && auth()->user()?->can('crm.convert'))
                                    <form action="{{ route('crm.leads.convert', $lead) }}" method="POST" class="inline-flex items-center gap-2">
                                        @csrf
                                        <label class="inline-flex items-center gap-1 text-2xs text-muted-foreground">
                                            <input type="checkbox" name="provision_portal_user" value="1" checked class="kt-checkbox kt-checkbox-sm">
                                            Portal Login
                                        </label>
                                        <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Convert to Client</button>
                                    </form>
                                @else
                                    <span class="text-xs text-muted-foreground">Onboarded</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-6 text-sm text-muted-foreground">No inquiries recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Deals Pipeline & Accounts Directory --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="kt-card">
            <div class="kt-card-header flex items-center justify-between">
                <h3 class="kt-card-title">Recent Deals</h3>
                <div class="flex items-center gap-1.5">
                    <a href="{{ route('crm.pipeline') }}" class="kt-btn kt-btn-xs kt-btn-primary">Kanban</a>
                    <a href="{{ route('crm.deals.index') }}" class="kt-btn kt-btn-xs kt-btn-outline">View All</a>
                </div>
            </div>
            <div class="kt-card-table kt-scrollable-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                        <tr>
                            <th>Deal & Account</th>
                            <th>Value</th>
                            <th>Stage</th>
                            <th>Close Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deals as $deal)
                            <tr>
                                <td>
                                    <a href="{{ route('crm.deals.show', $deal) }}" class="font-semibold text-foreground hover:text-primary hover:underline">
                                        {{ $deal->title }}
                                    </a>
                                    <div class="text-xs text-muted-foreground">{{ $deal->account?->name }} ({{ $deal->probability }}% win)</div>
                                </td>
                                <td class="font-semibold text-mono">{{ $deal->formattedValue() }}</td>
                                <td>
                                    @can('crm.deals.manage')
                                        <form action="{{ route('crm.deals.stage', $deal) }}" method="POST" class="inline-flex items-center gap-1">
                                            @csrf
                                            <select name="stage" class="kt-select kt-select-sm text-xs" onchange="this.form.submit()">
                                                @foreach(['discovery' => 'Discovery', 'site_visit' => 'Site Visit', 'proposal' => 'Proposal', 'negotiation' => 'Negotiation', 'closed_won' => 'Closed Won', 'closed_lost' => 'Closed Lost'] as $stageKey => $stageLabel)
                                                    <option value="{{ $stageKey }}" @selected($deal->stage === $stageKey)>{{ $stageLabel }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @else
                                        <span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ str_replace('_', ' ', $deal->stage) }}</span>
                                    @endcan
                                </td>
                                <td class="text-xs text-muted-foreground">{{ $deal->expected_close_date?->format('d/m/Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-6 text-sm text-muted-foreground">No deals in pipeline.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="kt-card">
            <div class="kt-card-header flex items-center justify-between">
                <h3 class="kt-card-title">Client Accounts</h3>
                <a href="{{ route('crm.accounts.index') }}" class="kt-btn kt-btn-xs kt-btn-outline">View All Accounts</a>
            </div>
            <div class="kt-card-table kt-scrollable-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Account</th>
                            <th>Type</th>
                            <th>Portal Access</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accounts as $account)
                            <tr>
                                <td class="font-mono text-xs font-semibold">
                                    <a href="{{ route('crm.accounts.show', $account) }}" class="text-primary hover:underline">
                                        {{ $account->code }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('crm.accounts.show', $account) }}" class="font-semibold text-foreground hover:text-primary hover:underline">
                                        {{ $account->name }}
                                    </a>
                                    <div class="text-xs text-muted-foreground">{{ $account->email ?? $account->phone ?? '—' }}</div>
                                </td>
                                <td><span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ $account->account_type }}</span></td>
                                <td>
                                    @if($account->portal_user_id)
                                        <span class="kt-badge kt-badge-xs kt-badge-success">Portal Enabled</span>
                                    @else
                                        <span class="kt-badge kt-badge-xs kt-badge-outline">Internal Only</span>
                                    @endif
                                </td>
                                <td class="text-xs text-muted-foreground">{{ $account->created_at?->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-6 text-sm text-muted-foreground">No client accounts registered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

