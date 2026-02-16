@extends('layouts.metronic.app')

@section('title', 'Edit Subscription Plan')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Billing</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Edit Plan: {{ $package->name }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Update pricing, metered rates, and feature entitlements.</p>
                </div>
                <a href="{{ route('packages.index') }}" class="kt-btn kt-btn-outline">Back to Packages</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <form id="editPackageForm" class="kt-form" action="{{ route('packages.update', $package->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="kt-form-item">
                        <label class="kt-form-label">Plan Name <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input type="text" name="name" class="kt-input" value="{{ old('name', $package->name) }}" required />
                        </div>
                    </div>
                    <div class="kt-form-item">
                        <label class="kt-form-label">Slug <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input type="text" name="slug" class="kt-input" value="{{ old('slug', $package->slug) }}" required />
                        </div>
                    </div>
                    <div class="kt-form-item">
                        <label class="kt-form-label">Price <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input type="number" step="0.01" name="price" class="kt-input" value="{{ old('price', $package->price) }}" required />
                        </div>
                    </div>
                    <div class="kt-form-item">
                        <label class="kt-form-label">Interval <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <select class="kt-select" name="interval" required>
                                <option value="month" {{ $package->interval === 'month' ? 'selected' : '' }}>Monthly</option>
                                <option value="year" {{ $package->interval === 'year' ? 'selected' : '' }}>Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="kt-form-item">
                        <label class="kt-form-label">Billing Model <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <select class="kt-select" name="billing_model" required>
                                <option value="flat_rate" {{ $package->billing_model === 'flat_rate' ? 'selected' : '' }}>
                                    Flat Rate (Fixed Price)
                                </option>
                                <option value="per_seat" {{ $package->billing_model === 'per_seat' ? 'selected' : '' }}>
                                    Per Seat (Per User)
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="kt-form-item lg:col-span-2">
                        <label class="kt-form-label">Description</label>
                        <div class="kt-form-control">
                            <textarea name="description" class="kt-textarea" rows="3">{{ old('description', $package->description) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="kt-form-item mt-6">
                    <label class="kt-form-label">Package Markup (%)</label>
                    <div class="grid gap-6 lg:grid-cols-[1fr_auto] items-center">
                        <div class="kt-form-control">
                            <input type="number" step="0.01" name="markup_percentage" class="kt-input"
                                value="{{ old('markup_percentage', $package->markup_percentage) }}" />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-foreground">
                            <input class="kt-switch" type="checkbox" name="is_active" value="1" id="isActive" {{ $package->is_active ? 'checked' : '' }} />
                            <span>Active (Visible for subscription)</span>
                        </label>
                    </div>
                    <p class="kt-form-description mt-2">Added to global markup for all metered usage.</p>
                </div>

                <div class="border-t border-border pt-6 mt-6">
                    <h3 class="text-sm font-semibold uppercase text-foreground">Metered Pricing (Rate Card)</h3>
                    <p class="text-xs text-muted-foreground mt-2">Define unit prices for metered metrics. If left blank, global defaults or free tier apply.</p>
                    <div class="kt-table-wrapper mt-4">
                        <table class="kt-table table-auto kt-table-border">
                            <thead>
                                <tr class="text-xs uppercase text-muted-foreground">
                                    <th>Metric</th>
                                    <th>Unit Price ($)</th>
                                    <th>Per X Units</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-muted-foreground">
                                @foreach($metrics as $metric)
                                    @php
                                        $price = $package->usagePrices->where('metric', $metric)->first();
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-foreground">{{ $metric->name }}</span>
                                                <span class="text-xs text-muted-foreground">Unit: {{ $metric->unit() }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" step="0.000001" name="usage_prices[{{ $metric->value }}][unit_price]"
                                                class="kt-input" value="{{ $price?->unit_price }}" placeholder="0.00">
                                        </td>
                                        <td>
                                            <input type="number" step="1" name="usage_prices[{{ $metric->value }}][unit_quantity]"
                                                class="kt-input" value="{{ $price?->unit_quantity ?? 1 }}" placeholder="1">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="border-t border-border pt-6 mt-6">
                    <h3 class="text-sm font-semibold uppercase text-foreground">Feature Matrix</h3>
                    <p class="text-xs text-muted-foreground mt-2">Toggle feature access and set per-plan limits.</p>
                    <div class="kt-table-wrapper mt-4">
                        <table class="kt-table table-auto kt-table-border">
                            <thead>
                                <tr class="text-xs uppercase text-muted-foreground">
                                    <th>Feature</th>
                                    <th>Include?</th>
                                    <th>Limit / Value</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-muted-foreground">
                                @foreach($features as $feature)
                                    @php
                                        $pivot = $package->features->find($feature->id)?->pivot;
                                        $isEnabled = !is_null($pivot);
                                        $value = $pivot?->value;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-foreground">{{ $feature->name }}</span>
                                                <span class="text-xs text-muted-foreground">{{ $feature->description }}</span>
                                                <span class="kt-badge kt-badge-outline kt-badge-secondary mt-2 w-fit">{{ ucfirst($feature->type) }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <input class="kt-checkbox" type="checkbox"
                                                name="features[{{ $feature->id }}][enabled]" value="1"
                                                id="feat_check_{{ $feature->id }}" {{ $isEnabled ? 'checked' : '' }}>
                                        </td>
                                        <td>
                                            @if($feature->type === 'boolean')
                                                <span class="text-xs text-muted-foreground">Enabled if checked</span>
                                                <input type="hidden" name="features[{{ $feature->id }}][value]" value="true">
                                            @else
                                                <input type="text" name="features[{{ $feature->id }}][value]" class="kt-input"
                                                    placeholder="e.g. 10 or Unlimited" value="{{ $value }}">
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="kt-form-actions mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('packages.index') }}" class="kt-btn kt-btn-outline">Discard</a>
                    <button type="submit" class="kt-btn kt-btn-primary">
                        <span class="indicator-label">Update Plan</span>
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection
