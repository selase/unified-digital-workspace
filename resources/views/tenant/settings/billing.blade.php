@extends('layouts.metronic.app')

@section('title', 'Billing Settings')

@section('content')
    @php
        $hasBillingEmail = filled($billingEmail);
        $hasTaxId = filled($taxId);
        $hasBillingAddress = filled($billingAddress);
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Settings</p>
                <h1 class="mt-2 text-2xl font-semibold text-foreground">Billing Settings</h1>
                <p class="mt-2 text-sm text-muted-foreground">Configure invoice and billing contact details.</p>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Billing Email</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $hasBillingEmail ? 'Configured' : 'Missing' }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Tax ID</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $hasTaxId ? 'Configured' : 'Missing' }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Billing Address</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $hasBillingAddress ? 'Configured' : 'Missing' }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-foreground">Invoice Details</h2>
                    <p class="text-xs text-muted-foreground">Update how invoices are addressed and delivered.</p>
                </div>
            </div>

            <form action="{{ route('tenant.settings.billing.update') }}" method="POST" class="mt-6 grid gap-6">
                @csrf
                <div class="kt-form-item">
                    <label class="kt-form-label">Billing Email</label>
                    <div class="kt-form-control">
                        <input type="email" name="billing_email" class="kt-input {{ isset($errors) && $errors->has('billing_email') ? '!border-destructive' : '' }}" placeholder="billing@company.com" value="{{ old('billing_email', $billingEmail) }}" />
                        <p class="mt-2 text-xs text-muted-foreground">Invoices will be sent to this address.</p>
                        @if(isset($errors) && $errors->has('billing_email'))
                            <p class="mt-2 text-xs text-destructive">{{ $errors->first('billing_email') }}</p>
                        @endif
                    </div>
                </div>

                <div class="kt-form-item">
                    <label class="kt-form-label">Tax ID / VAT Number</label>
                    <div class="kt-form-control">
                        <input type="text" name="tax_id" class="kt-input {{ isset($errors) && $errors->has('tax_id') ? '!border-destructive' : '' }}" placeholder="e.g. GB123456789" value="{{ old('tax_id', $taxId) }}" />
                        <p class="mt-2 text-xs text-muted-foreground">This will appear on your invoices.</p>
                        @if(isset($errors) && $errors->has('tax_id'))
                            <p class="mt-2 text-xs text-destructive">{{ $errors->first('tax_id') }}</p>
                        @endif
                    </div>
                </div>

                <div class="kt-form-item">
                    <label class="kt-form-label">Billing Address</label>
                    <div class="kt-form-control">
                        <textarea name="billing_address" class="kt-textarea {{ isset($errors) && $errors->has('billing_address') ? '!border-destructive' : '' }}" rows="3" placeholder="Street Address, City, Country">{{ old('billing_address', $billingAddress) }}</textarea>
                        <p class="mt-2 text-xs text-muted-foreground">Override your organization address for billing purposes.</p>
                        @if(isset($errors) && $errors->has('billing_address'))
                            <p class="mt-2 text-xs text-destructive">{{ $errors->first('billing_address') }}</p>
                        @endif
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button type="reset" class="kt-btn kt-btn-outline">Discard</button>
                    <button type="submit" class="kt-btn kt-btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </section>
@endsection
