@extends('layouts.metronic.app')

@section('title', 'Create New Invoice')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Billing</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Create Ad-Hoc Invoice</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Generate a draft invoice for a tenant outside the usual cycle.</p>
                </div>
                <a href="{{ route('admin.billing.invoices.index') }}" class="kt-btn kt-btn-outline">Back to Invoices</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <form action="{{ route('admin.billing.invoices.store') }}" method="POST" class="kt-form">
                @csrf
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="kt-form-item lg:col-span-2">
                        <label class="kt-form-label">Tenant <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <select name="tenant_id" class="kt-select" required>
                                <option value="">Select a Tenant</option>
                                @foreach($tenants as $tenant)
                                    <option value="{{ $tenant->id }}" {{ old('tenant_id') == $tenant->id ? 'selected' : '' }}>
                                        {{ $tenant->name }} ({{ $tenant->slug }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Currency <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <select name="currency" class="kt-select" required>
                                <option value="USD" {{ old('currency') == 'USD' ? 'selected' : '' }}>USD - US Dollar</option>
                                <option value="GHS" {{ old('currency') == 'GHS' ? 'selected' : '' }}>GHS - Ghanaian Cedi</option>
                                <option value="EUR" {{ old('currency') == 'EUR' ? 'selected' : '' }}>EUR - Euro</option>
                                <option value="GBP" {{ old('currency') == 'GBP' ? 'selected' : '' }}>GBP - British Pound</option>
                            </select>
                        </div>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Due Date <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input type="date" name="due_date" class="kt-input" value="{{ old('due_date', now()->addDays(7)->format('Y-m-d')) }}" required />
                            <p class="mt-2 text-xs text-muted-foreground">Default is 7 days from now.</p>
                        </div>
                    </div>
                </div>

                <div class="kt-form-actions mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('admin.billing.invoices.index') }}" class="kt-btn kt-btn-outline">Discard</a>
                    <button type="submit" class="kt-btn kt-btn-primary">
                        Create Draft
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection
