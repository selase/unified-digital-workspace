@extends('layouts.metronic.app')

@section('title', 'Global Rate Cards & Taxes')

@section('content')
    @php
        $configuredMetricCount = $globalPrices->count();
        $activeTaxCount = $taxes->where('is_active', true)->count();
        $compoundTaxCount = $taxes->where('is_compound', true)->count();
    @endphp

    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Billing</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Global Rate Cards & Taxes</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Define default usage pricing and tax rules.</p>
                </div>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Metrics Configured</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $configuredMetricCount }} / {{ count($metrics) }}</p>
                </div>
                <div class="rounded-lg bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Active Tax Rules</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $activeTaxCount }}</p>
                </div>
                <div class="rounded-lg bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Compound Rules</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $compoundTaxCount }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 kt-card p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">Default Rate Card</h2>
                        <p class="text-xs text-muted-foreground">Global system-wide unit prices for metered usage.</p>
                    </div>
                </div>

                @foreach(['USD', 'GHS'] as $rateCurrency)
                    <a class="kt-btn kt-btn-outline" href="{{ route('admin.billing.rate-cards.index', ['currency' => $rateCurrency]) }}">{{ $rateCurrency }} rates</a>
                @endforeach
                <p class="kt-form-description">Editing {{ $currency }} only. Blank removes a rate; zero explicitly means free. Missing GHS rates block invoicing for recorded usage.</p>
                @if($errors->any())<p role="alert" class="text-destructive">{{ $errors->first() }}</p>@endif
                <form action="{{ route('admin.billing.rate-cards.prices.update') }}" method="POST" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="usage_currency" value="{{ $currency }}">
                    <div class="overflow-x-auto">
                        <table class="kt-table table-auto kt-table-border">
                            <thead>
                                <tr class="text-xs uppercase text-muted-foreground">
                                    <th>Metric</th>
                                    <th>Unit Price ({{ $currency }})</th>
                                    <th>Per X Units</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-foreground">
                                @foreach($metrics as $metric)
                                    @php
                                        $price = $globalPrices->where('metric', $metric)->first();
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="flex flex-col">
                                                <span class="font-medium text-foreground">{{ $metric->name }}</span>
                                                <span class="text-xs text-muted-foreground">Unit: {{ $metric->unit() }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" step="0.000001" name="usage_prices[{{ $metric->value }}][unit_price]"
                                                   class="kt-input kt-input-sm"
                                                   value="{{ $price?->unit_price }}" placeholder="0.00">
                                        </td>
                                        <td>
                                            <input type="number" step="1" name="usage_prices[{{ $metric->value }}][unit_quantity]"
                                                   class="kt-input kt-input-sm"
                                                   value="{{ $price?->unit_quantity ?? 1 }}" placeholder="1">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="kt-btn kt-btn-primary">Update Global Rates</button>
                    </div>
                </form>
            </div>

            <div class="kt-card p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">Tax Configuration</h2>
                        <p class="text-xs text-muted-foreground">Flat rules use the subtotal; compound rules include earlier taxes. Blank country means global.</p>
                    </div>
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-primary" data-kt-modal-toggle="#add_tax_modal">
                        Add Tax Rule
                    </button>
                </div>

                <div class="mt-3 rounded-lg bg-muted/30 p-3 text-sm text-muted-foreground">
                    <p>Supplier country taxes: <strong>{{ config('billing.country_taxes_enabled', false) ? 'Enabled' : 'Disabled' }}</strong>.</p>
                    <p>Activating a country rule is not sufficient: the platform operator must also enable BILLING_COUNTRY_TAXES_ENABLED after confirming supplier VAT registration and approving commercial rates. Exemptions and zero-rating require administrator review; tenants cannot grant themselves an exemption.</p>
                </div>
                <p class="mt-3 text-sm text-muted-foreground">Ghana standard rates from 1 January 2026: NHIL 2.5%, GETFund 2.5%, VAT 15%, all on the same base (20% total). No COVID levy. Enable only after supplier VAT registration and commercial rates are approved; assess exemptions and zero-rating for each supply. Country alone does not make a transaction taxable. <a class="text-primary underline" href="https://gra.gov.gh/domestic-tax/tax-types/vat/" target="_blank" rel="noopener noreferrer">GRA rate guidance</a></p>
                @if($errors->any())
                    <div role="alert" class="mt-3 rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif
                <div class="mt-4 space-y-3">
                    @forelse($taxes as $tax)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-4">
                            <div class="flex flex-col">
                                <span class="font-medium text-foreground">{{ $tax->name }} ({{ $tax->rate }}%)</span>
                                <span class="text-xs text-muted-foreground">
                                    {{ $tax->country_code ?? 'Global' }} | {{ $tax->is_compound ? 'Compound' : 'Flat' }} | Priority: {{ $tax->priority }}
                                    @if(!$tax->is_active)
                                        <span class="kt-badge kt-badge-destructive ms-2">Disabled</span>
                                    @endif
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" class="kt-btn kt-btn-icon kt-btn-outline" data-kt-modal-toggle="#edit_tax_modal_{{ $tax->id }}">
                                    <i class="ki-filled ki-pencil text-base"></i>
                                </button>
                                <form action="{{ route('admin.billing.rate-cards.taxes.destroy', $tax) }}" method="POST" onsubmit="return confirm('Delete this tax rule?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="kt-btn kt-btn-icon kt-btn-outline">
                                        <i class="ki-filled ki-trash text-base"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-border p-5 text-center text-sm text-muted-foreground">
                            No tax rules configured yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection

@push('modals')
    <div class="kt-modal" data-kt-modal="true" id="add_tax_modal">
        <div class="kt-modal-content max-w-96 top-9">
            <div class="kt-modal-header">
                <h3 class="kt-modal-title">Add New Tax Rule</h3>
                <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" data-kt-modal-dismiss="true">
                    <i class="ki-filled ki-cross"></i>
                </button>
            </div>
            <form action="{{ route('admin.billing.rate-cards.taxes.store') }}" method="POST" class="kt-form">
                @csrf
                <div class="kt-modal-body p-6 space-y-4">
                    <div class="kt-form-item">
                        <label class="kt-form-label">Rule Name <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input type="text" name="name" class="kt-input" placeholder="e.g. VAT" required>
                        </div>
                    </div>
                    <div class="kt-form-item">
                        <label for="new_tax_country" class="kt-form-label">Country code (optional)</label>
                        <input id="new_tax_country" type="text" name="country_code" class="kt-input" maxlength="2" placeholder="GH" value="{{ old('country_code') }}" aria-describedby="new_tax_country_help">
                        <p id="new_tax_country_help" class="text-xs text-muted-foreground">Two-letter code. Leave blank only for a global rule that can apply in every country.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="kt-form-item">
                            <label class="kt-form-label">Rate (%) <span class="text-destructive">*</span></label>
                            <div class="kt-form-control">
                                <input type="number" step="0.01" name="rate" class="kt-input" placeholder="15.00" required>
                            </div>
                        </div>
                        <div class="kt-form-item">
                            <label class="kt-form-label">Priority <span class="text-destructive">*</span></label>
                            <div class="kt-form-control">
                                <input type="number" name="priority" class="kt-input" value="1" required>
                            </div>
                        </div>
                    </div>
                    <label class="flex items-center justify-between gap-4 rounded-lg border border-border p-3 text-sm text-foreground">
                        <span>Compound Tax?</span>
                        <input class="kt-switch" type="checkbox" name="is_compound" value="1">
                    </label>
                    <label class="flex items-center justify-between gap-4 rounded-lg bg-muted/30 p-3 text-sm text-foreground">
                        <span>Active rule (after applicability review)</span>
                        <input type="hidden" name="is_active" value="0">
                        <input class="kt-switch" type="checkbox" name="is_active" value="1">
                    </label>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-border p-4">
                    <button type="button" class="kt-btn kt-btn-outline" data-kt-modal-dismiss="true">Cancel</button>
                    <button type="submit" class="kt-btn kt-btn-primary">Create Rule</button>
                </div>
            </form>
        </div>
    </div>

    @foreach($taxes as $tax)
        <div class="kt-modal" data-kt-modal="true" id="edit_tax_modal_{{ $tax->id }}">
            <div class="kt-modal-content max-w-96 top-9">
                <div class="kt-modal-header">
                    <h3 class="kt-modal-title">Edit Tax: {{ $tax->name }}</h3>
                    <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" data-kt-modal-dismiss="true">
                        <i class="ki-filled ki-cross"></i>
                    </button>
                </div>
                <form action="{{ route('admin.billing.rate-cards.taxes.update', $tax) }}" method="POST" class="kt-form">
                    @csrf
                    @method('PUT')
                    <div class="kt-modal-body p-6 space-y-4">
                        <div class="kt-form-item">
                            <label class="kt-form-label">Rule Name <span class="text-destructive">*</span></label>
                            <div class="kt-form-control">
                                <input type="text" name="name" class="kt-input" value="{{ $tax->name }}" required>
                            </div>
                        </div>
                        <div class="kt-form-item">
                            <label for="tax_country_{{ $tax->id }}" class="kt-form-label">Country code (optional)</label>
                            <input id="tax_country_{{ $tax->id }}" type="text" name="country_code" class="kt-input" maxlength="2" placeholder="GH" value="{{ $tax->country_code }}" aria-describedby="tax_country_help_{{ $tax->id }}">
                            <p id="tax_country_help_{{ $tax->id }}" class="text-xs text-muted-foreground">Blank makes this a global rule. A country rule also requires an explicit applicability decision when invoicing.</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="kt-form-item">
                                <label class="kt-form-label">Rate (%) <span class="text-destructive">*</span></label>
                                <div class="kt-form-control">
                                    <input type="number" step="0.01" name="rate" class="kt-input" value="{{ $tax->rate }}" required>
                                </div>
                            </div>
                            <div class="kt-form-item">
                                <label class="kt-form-label">Priority <span class="text-destructive">*</span></label>
                                <div class="kt-form-control">
                                    <input type="number" name="priority" class="kt-input" value="{{ $tax->priority }}" required>
                                </div>
                            </div>
                        </div>
                        <label class="flex items-center justify-between gap-4 rounded-lg border border-border p-3 text-sm text-foreground">
                            <span>Compound Tax?</span>
                            <input class="kt-switch" type="checkbox" name="is_compound" value="1" {{ $tax->is_compound ? 'checked' : '' }}>
                        </label>
                        <label class="flex items-center justify-between gap-4 rounded-lg border border-border p-3 text-sm text-foreground">
                            <span>Is Active?</span>
                            <input class="kt-switch" type="checkbox" name="is_active" value="1" {{ $tax->is_active ? 'checked' : '' }}>
                        </label>
                    </div>
                    <div class="flex items-center justify-end gap-3 border-t border-border p-4">
                        <button type="button" class="kt-btn kt-btn-outline" data-kt-modal-dismiss="true">Cancel</button>
                        <button type="submit" class="kt-btn kt-btn-primary">Save Rule</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endpush
