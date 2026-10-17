@extends('layouts.metronic.app')

@section('title', 'Vendor Marketplace, Proforma & Escrow Settlement Hub')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Vendor Marketplace, Proforma & Escrow Settlement Hub"
        description="Unified portal for real estate experts (surveyors, land lawyers, GIA architects, GhIE engineers, building materials) and facility artisans (electricians, plumbers, landscaping, water) with double-entry escrow and 3-way commission routing."
    />

    {{-- Financial & Escrow Telemetry --}}
    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-6 gap-4">
        <div class="kt-card p-4">
            <span class="text-2xs uppercase font-semibold tracking-wider text-muted-foreground">Verified Providers</span>
            <div class="mt-1.5 text-xl font-bold text-foreground">{{ $metrics['vendors_count'] }}</div>
        </div>
        <div class="kt-card p-4">
            <span class="text-2xs uppercase font-semibold tracking-wider text-muted-foreground">Held in Escrow</span>
            <div class="mt-1.5 text-xl font-bold text-primary">GHS {{ $metrics['held_ghs'] }}</div>
        </div>
        <div class="kt-card p-4">
            <span class="text-2xs uppercase font-semibold tracking-wider text-muted-foreground">Vendor Payouts</span>
            <div class="mt-1.5 text-xl font-bold text-success">GHS {{ $metrics['vendor_payout_ghs'] }}</div>
        </div>
        <div class="kt-card p-4">
            <span class="text-2xs uppercase font-semibold tracking-wider text-muted-foreground">Tenant / Estate Comm.</span>
            <div class="mt-1.5 text-xl font-bold text-foreground">GHS {{ $metrics['tenant_commission_ghs'] }}</div>
        </div>
        <div class="kt-card p-4">
            <span class="text-2xs uppercase font-semibold tracking-wider text-muted-foreground">Platform Owner Comm.</span>
            <div class="mt-1.5 text-xl font-bold text-foreground">GHS {{ $metrics['platform_commission_ghs'] }}</div>
        </div>
        <div class="kt-card p-4">
            <span class="text-2xs uppercase font-semibold tracking-wider text-muted-foreground">Active Disputes</span>
            <div class="mt-1.5 text-xl font-bold {{ $metrics['active_disputes'] > 0 ? 'text-danger' : 'text-foreground' }}">
                {{ $metrics['active_disputes'] }}
            </div>
        </div>
    </div>

    {{-- Top Row: Commission Rate Cards + Onboard Provider + Dispatch Service Job --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- 1. Explicit Commission Rate Card Configuration --}}
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title text-sm font-semibold">3-Way Commission Rate Cards</h3>
            </div>
            <div class="kt-card-body p-5 space-y-4">
                <p class="text-xs text-muted-foreground">
                    Zero invented fees: commissions are strictly computed from approved category rate cards and disclosed on every Proforma Invoice prior to payment.
                </p>
                @can('commissions.configure')
                    <form action="{{ route('vendor-marketplace.rate-cards.upsert') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="kt-form-label text-xs">Service / Product Category *</label>
                            <select name="category" class="kt-select" required>
                                @foreach($categories as $catKey => $catLabel)
                                    <option value="{{ $catKey }}">{{ $catLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="kt-form-label text-xs">Tenant / Estate Owner % *</label>
                                <input type="number" step="0.01" min="0" max="25" name="tenant_commission_percent" value="5.00" class="kt-input" required>
                            </div>
                            <div>
                                <label class="kt-form-label text-xs">Platform Owner % *</label>
                                <input type="number" step="0.01" min="0" max="25" name="platform_commission_percent" value="2.50" class="kt-input" required>
                            </div>
                        </div>
                        <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">Approve Rate Card</button>
                    </form>
                @endcan

                <div class="divide-y divide-border border-t border-border pt-3">
                    @forelse($rateCards as $card)
                        <div class="py-2 flex items-center justify-between text-xs">
                            <span class="font-medium text-foreground">{{ $card->label }}</span>
                            <div class="flex items-center gap-2">
                                <span class="kt-badge kt-badge-xs kt-badge-outline">Tenant: {{ number_format($card->tenant_commission_bps / 100, 2) }}%</span>
                                <span class="kt-badge kt-badge-xs kt-badge-primary">Platform: {{ number_format($card->platform_commission_bps / 100, 2) }}%</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-xs text-muted-foreground py-2">No rate cards configured yet (defaults to 0.00% commission).</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- 2. Onboard Vendor / Service Provider / Artisan --}}
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title text-sm font-semibold">Onboard Expert / Artisan / Supplier</h3>
            </div>
            <div class="kt-card-body p-5">
                <form action="{{ route('vendor-marketplace.vendors.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="kt-form-label text-xs">Business / Practice Name *</label>
                        <input type="text" name="business_name" class="kt-input" placeholder="e.g. Meridian Geomatic Surveyors Ltd" required>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="kt-form-label text-xs">Specialty Category *</label>
                            <select name="category" class="kt-select" required>
                                @foreach($categories as $catKey => $catLabel)
                                    <option value="{{ $catKey }}">{{ $catLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Professional License (GIA/GhIE/GhIS)</label>
                            <input type="text" name="license_number" class="kt-input" placeholder="e.g. GhIS-2026-441">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="kt-form-label text-xs">Phone</label>
                            <input type="text" name="phone" class="kt-input" placeholder="+233...">
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Email</label>
                            <input type="email" name="email" class="kt-input" placeholder="desk@provider.com">
                        </div>
                    </div>
                    <button type="submit" class="kt-btn kt-btn-outline w-full justify-center">Register Service Provider</button>
                </form>
            </div>
        </div>

        {{-- 3. Dispatch Service Request Job (Real Estate or Facilities) --}}
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title text-sm font-semibold">Request Expert / Artisan / Materials</h3>
            </div>
            <div class="kt-card-body p-5">
                <form action="{{ route('vendor-marketplace.jobs.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="kt-form-label text-xs">Job / Order Title *</label>
                        <input type="text" name="title" class="kt-input" placeholder="e.g. Cadastral Site Plan & Beacon Pillar Installation" required>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="kt-form-label text-xs">Category *</label>
                            <select name="category" class="kt-select" required>
                                @foreach($categories as $catKey => $catLabel)
                                    <option value="{{ $catKey }}">{{ $catLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Origin Module *</label>
                            <select name="source_module" class="kt-select" required>
                                <option value="real_estate">Real Estate / Land Acquisition</option>
                                <option value="facilities">Facilities & Estate Occupant Request</option>
                                <option value="direct">Direct Institutional Order</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="kt-form-label text-xs">Assign Provider (Optional)</label>
                            <select name="vendor_profile_id" class="kt-select">
                                <option value="">Open to Verified Providers</option>
                                @foreach($vendors as $vendor)
                                    <option value="{{ $vendor->id }}">{{ $vendor->business_name }} ({{ $vendor->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Plot / Facility Location</label>
                            <input type="text" name="location" class="kt-input" placeholder="e.g. Plot 14B, Prampram Enclave">
                        </div>
                    </div>
                    <button type="submit" class="kt-btn kt-btn-outline w-full justify-center">Dispatch Service Job</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Service Jobs & Proforma Invoice Submission --}}
    <div class="kt-card">
        <div class="kt-card-header">
            <h3 class="kt-card-title">Service Jobs & Provider Proforma Invoice Desk</h3>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Job #</th>
                        <th>Title & Origin</th>
                        <th>Category</th>
                        <th>Assigned Provider</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Submit Proforma Invoice</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jobs as $job)
                        <tr>
                            <td class="font-mono text-xs font-semibold">{{ $job->job_number }}</td>
                            <td>
                                <div class="font-semibold text-foreground">{{ $job->title }}</div>
                                <div class="text-xs text-muted-foreground uppercase">{{ str_replace('_', ' ', $job->source_module) }} • {{ $job->location ?? 'On-site' }}</div>
                            </td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-outline">{{ $categories[$job->category] ?? $job->category }}</span></td>
                            <td class="text-xs">{{ $job->vendorProfile?->business_name ?? 'Unassigned' }}</td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-primary uppercase">{{ str_replace('_', ' ', $job->status) }}</span></td>
                            <td class="text-xs text-muted-foreground">{{ $job->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                @if(in_array($job->status, ['open', 'assigned'], true))
                                    <form action="{{ route('vendor-marketplace.proformas.store', $job) }}" method="POST" class="inline-flex items-center gap-2">
                                        @csrf
                                        <select name="vendor_profile_id" class="kt-select kt-select-sm text-xs w-36" required>
                                            @foreach($vendors as $vendor)
                                                <option value="{{ $vendor->id }}" @selected($job->vendor_profile_id === $vendor->id)>{{ $vendor->business_name }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" step="0.01" min="1" name="gross_amount_ghs" class="kt-input kt-input-sm w-28 text-xs" placeholder="GHS Amount" required>
                                        <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Issue Proforma</button>
                                    </form>
                                @else
                                    <span class="text-xs text-muted-foreground">Quoted / In Escrow</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-6 text-sm text-muted-foreground">No service jobs dispatched yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Proforma Invoices & Pre-Payment Commission Disclosure + Escrow Accounts --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title">Proforma Invoices & Pre-Payment Fee Disclosure</h3>
            </div>
            <div class="kt-card-table kt-scrollable-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                        <tr>
                            <th>Proforma #</th>
                            <th>Gross Quote</th>
                            <th>Disclosed 3-Way Split</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($proformas as $proforma)
                            <tr>
                                <td>
                                    <div class="font-mono text-xs font-semibold">{{ $proforma->invoice_number }}</div>
                                    <div class="text-xs text-muted-foreground">{{ $proforma->vendorProfile?->business_name }}</div>
                                </td>
                                <td class="font-bold text-foreground">{{ $proforma->formattedGross() }}</td>
                                <td class="text-xs space-y-0.5">
                                    <div>Vendor Net: <strong>{{ $proforma->formattedNetVendor() }}</strong></div>
                                    <div class="text-muted-foreground">
                                        Tenant ({{ number_format($proforma->tenant_commission_bps / 100, 2) }}%): GHS {{ number_format($proforma->tenant_commission_pesewas / 100, 2) }}
                                        • Platform ({{ number_format($proforma->platform_commission_bps / 100, 2) }}%): GHS {{ number_format($proforma->platform_commission_pesewas / 100, 2) }}
                                    </div>
                                </td>
                                <td class="text-end">
                                    @if($proforma->status === 'submitted' && auth()->user()?->can('escrow.manage'))
                                        <form action="{{ route('vendor-marketplace.escrows.fund', $proforma) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Fund Escrow</button>
                                        </form>
                                    @else
                                        <span class="kt-badge kt-badge-xs kt-badge-success uppercase">{{ $proforma->status }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-6 text-sm text-muted-foreground">No proforma invoices submitted yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title">Escrow Accounts & Dispute Governance</h3>
            </div>
            <div class="kt-card-table kt-scrollable-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                        <tr>
                            <th>Escrow #</th>
                            <th>Held Balance</th>
                            <th>Status</th>
                            <th class="text-end">Settlement / Dispute Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($escrows as $escrow)
                            <tr>
                                <td>
                                    <div class="font-mono text-xs font-semibold">{{ $escrow->escrow_number }}</div>
                                    <div class="text-xs text-muted-foreground">{{ $escrow->vendorProfile?->business_name }}</div>
                                </td>
                                <td class="font-bold text-mono">{{ $escrow->formattedHeldBalance() }}</td>
                                <td>
                                    @if($escrow->status === 'frozen_disputed')
                                        <span class="kt-badge kt-badge-xs kt-badge-danger">FROZEN (DISPUTED)</span>
                                    @elseif($escrow->status === 'released')
                                        <span class="kt-badge kt-badge-xs kt-badge-success">RELEASED (3-WAY SPLIT)</span>
                                    @elseif($escrow->status === 'refunded')
                                        <span class="kt-badge kt-badge-xs kt-badge-warning">REFUNDED TO BUYER</span>
                                    @else
                                        <span class="kt-badge kt-badge-xs kt-badge-primary uppercase">{{ $escrow->status }}</span>
                                    @endif
                                </td>
                                <td class="text-end space-y-1">
                                    @if($escrow->status === 'funded')
                                        <div class="flex items-center justify-end gap-1.5">
                                            <form action="{{ route('vendor-marketplace.escrows.release', $escrow) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-success">Release Payout</button>
                                            </form>
                                            <form action="{{ route('vendor-marketplace.escrows.disputes.open', $escrow) }}" method="POST" class="inline-flex gap-1">
                                                @csrf
                                                <input type="hidden" name="reason" value="Milestone inspection variance reported by client.">
                                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-danger">Freeze / Dispute</button>
                                            </form>
                                        </div>
                                    @elseif($escrow->status === 'frozen_disputed' && $escrow->disputes->where('status', 'open')->first() && auth()->user()?->can('escrow.disputes.resolve'))
                                        @php
                                            $openDispute = $escrow->disputes->where('status', 'open')->first();
                                        @endphp
                                        <form action="{{ route('vendor-marketplace.escrows.disputes.resolve', $openDispute) }}" method="POST" class="inline-flex items-center gap-1">
                                            @csrf
                                            <select name="resolution_type" class="kt-select kt-select-sm text-xs">
                                                <option value="release_to_vendor">Release to Provider</option>
                                                <option value="refund_to_buyer">Refund to Buyer</option>
                                            </select>
                                            <input type="hidden" name="resolution_notes" value="Adjudicated by institutional escrow committee.">
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Resolve</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-muted-foreground">{{ $escrow->settled_at?->format('d/m/Y H:i') ?? '—' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-6 text-sm text-muted-foreground">No active escrow accounts.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

