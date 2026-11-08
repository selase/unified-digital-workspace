@extends('layouts.metronic.app')

@section('title', 'Discounts')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <p class="text-xs uppercase tracking-wide text-muted-foreground">Billing</p>
            <h1 class="mt-2 text-2xl font-semibold text-foreground">Discounts</h1>
            <p class="mt-2 text-sm text-muted-foreground">A discount is applied automatically to every invoice generated while it is active and its dates overlap the billed month. For a one-off change on a single invoice, use Add Adjustment on that invoice instead.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-lg bg-destructive/10 p-4 text-sm text-destructive">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <div class="kt-card p-6">
            <h2 class="text-lg font-semibold text-foreground">New discount</h2>
            <form action="{{ route('admin.billing.discounts.store') }}" method="POST" class="mt-4 grid gap-4 md:grid-cols-3">
                @csrf
                <div>
                    <label class="text-xs font-semibold text-muted-foreground" for="tenant_id">Organisation</label>
                    <select id="tenant_id" name="tenant_id" class="kt-select mt-1 w-full" required>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected(old('tenant_id') === $tenant->id)>{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-muted-foreground" for="name">Name</label>
                    <input id="name" name="name" class="kt-input mt-1 w-full" value="{{ old('name') }}" placeholder="e.g. Loyalty discount" required>
                </div>
                <div>
                    <label class="text-xs font-semibold text-muted-foreground" for="reason">Reason (internal)</label>
                    <input id="reason" name="reason" class="kt-input mt-1 w-full" value="{{ old('reason') }}" placeholder="e.g. Agreed on a call, 7 Nov" required>
                </div>
                <div>
                    <label class="text-xs font-semibold text-muted-foreground" for="type">Type</label>
                    <select id="type" name="type" class="kt-select mt-1 w-full">
                        <option value="percent" @selected(old('type') === 'percent')>Percentage</option>
                        <option value="fixed" @selected(old('type') === 'fixed')>Fixed amount</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-muted-foreground" for="value">Value (percent, or amount)</label>
                    <input id="value" name="value" type="number" step="0.01" min="0.01" class="kt-input mt-1 w-full" value="{{ old('value') }}" required>
                </div>
                <div>
                    <label class="text-xs font-semibold text-muted-foreground" for="currency">Currency (fixed amounts only)</label>
                    <select id="currency" name="currency" class="kt-select mt-1 w-full">
                        <option value="">n/a</option>
                        <option value="GHS" @selected(old('currency') === 'GHS')>GHS</option>
                        <option value="USD" @selected(old('currency') === 'USD')>USD</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-muted-foreground" for="starts_on">Starts on</label>
                    <input id="starts_on" name="starts_on" type="date" class="kt-input mt-1 w-full" value="{{ old('starts_on', now()->toDateString()) }}" required>
                </div>
                <div>
                    <label class="text-xs font-semibold text-muted-foreground" for="ends_on">Ends on (optional)</label>
                    <input id="ends_on" name="ends_on" type="date" class="kt-input mt-1 w-full" value="{{ old('ends_on') }}">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="kt-btn kt-btn-primary">Save discount</button>
                </div>
            </form>
        </div>

        <div class="kt-card p-6">
            <h2 class="text-lg font-semibold text-foreground">All discounts</h2>
            <div class="mt-4 grid gap-3">
                @forelse ($discounts as $discount)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-muted/30 p-4">
                        <div>
                            <p class="font-semibold text-foreground">{{ $discount->tenant?->name }}: {{ $discount->name }} ({{ $discount->label() }})</p>
                            <p class="text-xs text-muted-foreground">{{ $discount->reason }} · from {{ $discount->starts_on->format('j M Y') }} {{ $discount->ends_on ? 'to '.$discount->ends_on->format('j M Y') : 'until switched off' }}</p>
                        </div>
                        <form action="{{ route('admin.billing.discounts.toggle', $discount) }}" method="POST">
                            @csrf @method('PATCH')
                            <button type="submit" class="kt-btn kt-btn-sm {{ $discount->is_active ? 'kt-btn-outline' : 'kt-btn-primary' }}">{{ $discount->is_active ? 'Switch off' : 'Reactivate' }}</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">No discounts yet.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
