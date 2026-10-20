@extends('layouts.metronic.app')

@section('title', 'Create Package')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Billing</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Create Subscription Package</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Set pricing, limits, and entitlements for a new plan.</p>
                </div>
                <a href="{{ route('packages.index') }}" class="kt-btn kt-btn-outline">Back to Packages</a>
            </div>
        </div>

        <div class="kt-card p-6">
            <form id="createPackageForm" class="kt-form" action="{{ route('packages.store') }}" method="POST">
                @csrf

                @if($errors->any())
                    <div role="alert" class="text-destructive">{{ $errors->first() }}</div>
                @endif
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="kt-form-item">
                        <label class="kt-form-label" for="slug">Slug</label>
                        <input id="slug" name="slug" class="kt-input" value="{{ old('slug') }}" required>
                    </div>
                    <div class="kt-form-item">
                        <label class="kt-form-label" for="billing_model">Billing Model</label>
                        <select id="billing_model" name="billing_model" class="kt-select">
                            <option value="flat_rate" @selected(old('billing_model') === 'flat_rate')>Flat Rate</option>
                            <option value="per_seat" @selected(old('billing_model') === 'per_seat')>Per Seat</option>
                        </select>
                    </div>
                    <div class="kt-form-item">
                        <label class="kt-form-label" for="price_ghs">GHS Price</label>
                        <input id="price_ghs" type="number" min="0" step="0.01" name="price_ghs" class="kt-input" value="{{ old('price_ghs') }}">
                        <p class="kt-form-description">Blank means not configured; zero explicitly means free. No USD conversion is applied.</p>
                    </div>
                    <div class="kt-form-item">
                        <label class="kt-form-label">Package Name <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input type="text" name="name" class="kt-input" placeholder="e.g. Enterprise"
                                value="{{ old('name') }}" required />
                        </div>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">USD Price <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input type="number" step="0.01" name="price" class="kt-input" placeholder="e.g. 499"
                                value="{{ old('price') }}" required />
                        </div>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Interval <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <select name="interval" class="kt-select" required>
                                <option value="month" {{ old('interval') === 'month' ? 'selected' : '' }}>Monthly</option>
                                <option value="year" {{ old('interval') === 'year' ? 'selected' : '' }}>Yearly</option>
                            </select>
                        </div>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Status</label>
                        <div class="kt-form-control">
                            <select name="is_active" class="kt-select">
                                <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="kt-form-item lg:col-span-2">
                        <label class="kt-form-label">Description</label>
                        <div class="kt-form-control">
                            <textarea name="description" class="kt-textarea" rows="3">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="kt-form-actions mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('packages.index') }}" class="kt-btn kt-btn-outline">Discard</a>
                    <button type="submit" class="kt-btn kt-btn-primary">
                        <span class="indicator-label">Create Package</span>
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection
