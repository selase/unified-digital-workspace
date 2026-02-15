@extends('layouts.metronic.app')

@section('title', 'Global Rate Cards & Taxes')

@section('content')
    @php
        $configuredMetricCount = $globalPrices->count();
        $activeTaxCount = $taxes->where('is_active', true)->count();
        $compoundTaxCount = $taxes->where('is_compound', true)->count();
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Billing</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Global Rate Cards & Taxes</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Define default usage pricing and tax rules.</p>
                </div>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Metrics Configured</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $configuredMetricCount }} / {{ count($metrics) }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Active Tax Rules</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $activeTaxCount }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Compound Rules</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $compoundTaxCount }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 rounded-xl border border-border bg-background p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">Default Rate Card</h2>
                        <p class="text-xs text-muted-foreground">Global system-wide unit prices for metered usage.</p>
                    </div>
                </div>

                <form action="{{ route('admin.billing.rate-cards.prices.update') }}" method="POST" class="mt-4 space-y-4">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="kt-table">
                            <thead>
                                <tr class="text-xs uppercase text-muted-foreground">
                                    <th>Metric</th>
                                    <th>Unit Price ($)</th>
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

            <div class="rounded-xl border border-border bg-background p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">Tax Configuration</h2>
                        <p class="text-xs text-muted-foreground">Applied to subtotal sequentially.</p>
                    </div>
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-primary" data-kt-modal-toggle="#add_tax_modal">
                        Add Tax Rule
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    @forelse($taxes as $tax)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-4">
                            <div class="flex flex-col">
                                <span class="font-medium text-foreground">{{ $tax->name }} ({{ $tax->rate }}%)</span>
                                <span class="text-xs text-muted-foreground">
                                    {{ $tax->is_compound ? 'Compound' : 'Flat' }} | Priority: {{ $tax->priority }}
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
