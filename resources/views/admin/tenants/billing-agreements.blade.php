@extends('layouts.metronic.app')

@section('title', 'Billing agreements & access')

@section('content')
    @php
        $selectedPlan = is_string(old('plan_slug')) ? old('plan_slug') : 'essentials';
        $selectedPackages = is_array(old('package_slugs')) ? old('package_slugs') : [];
    @endphp
    <section class="grid gap-6">
        <div class="kt-card p-6">
            <h1 class="text-2xl font-semibold text-foreground">Billing agreements &amp; access — {{ $tenant->name }}</h1>
            <a href="{{ route('tenants.edit', $tenant->uuid) }}" class="kt-btn kt-btn-outline mt-4">Back to tenant</a>
            <p class="mt-4 text-sm text-muted-foreground">Existing customer agreements remain in force until explicit adoption takes effect. New agreements preserve their rate snapshot and do not rewrite earlier agreements or swap legacy packages.</p>
            @if(session('success'))
                <p role="status" class="mt-4 text-sm text-foreground">{{ session('success') }}</p>
            @endif
            @if($errors->any())
                <div role="alert" class="mt-4 text-sm text-destructive">
                    <p>Please correct the following:</p>
                    <ul class="list-disc pl-6">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
        </div>

        <div class="kt-card p-6">
            <h2 class="text-lg font-semibold text-foreground">Current and scheduled agreements</h2>
            @unless($activeAgreement)
                <p class="mt-3 text-sm text-muted-foreground">No active hybrid agreement. Legacy billing and access remain in effect until a scheduled agreement starts.</p>
            @endunless
            <div class="mt-4 grid gap-4">
                @forelse($agreements as $agreement)
                    @php($plan = data_get($agreement->snapshot, 'hybrid.website_plans.'.$agreement->plan_slug, []))
                    <article class="rounded-lg border border-border p-4 text-sm text-foreground">
                        <h3 class="font-semibold">{{ $plan['name'] ?? $agreement->plan_slug }} — {{ $agreement->effective_from->format('Y-m-d') }}
                            ({{ $agreement->is($activeAgreement) ? 'Current' : ($agreement->effective_from->isFuture() ? 'Scheduled' : 'Historical') }})
                        </h3>
                        <p class="mt-2">{{ $agreement->currency }} {{ number_format($plan[$agreement->currency] ?? 0, 2) }}/month · {{ $agreement->editor_seats }} editor seats ({{ $plan['editors'] ?? 0 }} included).</p>
                        <p>Extra editor: {{ $agreement->currency }} {{ number_format(data_get($agreement->snapshot, 'hybrid.extra_editor.'.$agreement->currency, 0), 2) }}/month. One-time setup: {{ $agreement->setup_required ? $agreement->currency.' '.number_format(data_get($plan, 'setup.'.$agreement->currency, 0), 2) : 'Not required' }}.</p>
                        <p>Modules: {{ collect(data_get($agreement->snapshot, 'selected_packages', []))->map(fn ($slug) => data_get($agreement->snapshot, 'hybrid.packages.'.$slug.'.name', $slug))->join(', ') ?: 'Included website modules only' }}.</p>
                        <details class="mt-3">
                            <summary class="cursor-pointer">View immutable rate snapshot (modules, access, allowances and usage)</summary>
                            <dl class="mt-3 grid gap-2">
                                <div><dt class="font-semibold">Included website capacity</dt><dd>{{ $plan['sites'] ?? 1 }} website; {{ $plan['domains'] ?? 1 }} connected domain; {{ $plan['files_gib'] ?? 0 }} GiB files; {{ $plan['database_gib'] ?? 0 }} GiB logical website database; {{ $plan['bandwidth_gib'] ?? 0 }} GiB application-delivered bandwidth per month.</dd></div>
                                @foreach(data_get($agreement->snapshot, 'hybrid.access', []) as $rate)
                                    <div><dt class="font-semibold">{{ $rate['name'] }} active access</dt><dd>{{ $agreement->currency }} {{ number_format($rate[$agreement->currency], 2) }} per distinct monthly user before graduated discounts.</dd></div>
                                @endforeach
                                <div><dt class="font-semibold">Postpaid email</dt><dd>{{ $agreement->currency }} {{ number_format($agreement->snapshot['meters']['emails.count'][$agreement->currency] ?? 0, 2) }} per 1,000 eligible recipients. Prepaid and system mail are excluded.</dd></div>
                            </dl>
                        </details>
                    </article>
                @empty
                    <p class="text-sm text-muted-foreground">No agreements scheduled. Use the adoption form below when the customer has agreed to transition.</p>
                @endforelse
            </div>
        </div>

        <form method="post" action="{{ route('tenants.billing-agreements.store', $tenant->uuid) }}" class="kt-card kt-form p-6">
            @csrf
            <h2 class="text-lg font-semibold text-foreground">Schedule explicit adoption</h2>
            <p class="mt-2 text-sm text-muted-foreground">Rates shown are the current rate card for a new agreement. Module subscriptions and active-user access charges are separate from the website plan.</p>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div class="kt-form-item">
                    <label for="plan_slug" class="kt-form-label">Website plan</label>
                    <select id="plan_slug" name="plan_slug" class="kt-select" required>
                        @foreach($hybrid['website_plans'] as $slug => $plan)
                            <option value="{{ $slug }}" @selected($selectedPlan === $slug)>{{ $plan['name'] }} — {{ $currency }} {{ number_format($plan[$currency], 2) }}/month; {{ $plan['editors'] }} editors included; setup {{ $currency }} {{ number_format($plan['setup'][$currency], 2) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="kt-form-item">
                    <label for="currency_display" class="kt-form-label">Existing billing currency</label>
                    <input id="currency_display" class="kt-input" value="{{ $currency }}" readonly>
                    <input name="currency" type="hidden" value="{{ $currency }}">
                </div>
                <div class="kt-form-item">
                    <label for="effective_from" class="kt-form-label">Effective from</label>
                    <input id="effective_from" name="effective_from" type="date" class="kt-input" required min="{{ today()->day === 1 ? today()->toDateString() : today()->addMonth()->startOfMonth()->toDateString() }}" value="{{ old('effective_from', today()->day === 1 ? today()->toDateString() : today()->addMonth()->startOfMonth()->toDateString()) }}">
                    <p class="text-xs text-muted-foreground">First day of a future month, or today if today is the first. No backdating.</p>
                </div>
                <div class="kt-form-item">
                    <label for="editor_seats" class="kt-form-label">Total editor seats</label>
                    <input id="editor_seats" name="editor_seats" type="number" class="kt-input" min="{{ min(array_column($hybrid['website_plans'], 'editors')) }}" required value="{{ old('editor_seats', $hybrid['website_plans'][$selectedPlan]['editors'] ?? 2) }}">
                    <p class="text-xs text-muted-foreground">Must cover the selected plan's included editors. Each additional seat: {{ $currency }} {{ number_format($hybrid['extra_editor'][$currency], 2) }}/month.</p>
                </div>
            </div>
            <fieldset class="mt-4 grid gap-3">
                <legend class="kt-form-label">Separate organisation module subscriptions</legend>
                @foreach($hybrid['packages'] as $slug => $package)
                    <label class="flex items-center gap-3 text-sm text-foreground">
                        <input type="checkbox" name="package_slugs[]" value="{{ $slug }}" @checked(in_array($slug, $selectedPackages, true))>
                        <span>{{ $package['name'] }} — {{ $currency }} {{ number_format($package[$currency], 2) }}/month</span>
                    </label>
                @endforeach
            </fieldset>
            <label for="setup_required" class="kt-form-label mt-4">One-time setup</label>
            <select id="setup_required" name="setup_required" class="kt-select" required>
                <option value="0" @selected((string) old('setup_required', '0') === '0')>No setup fee</option>
                <option value="1" @selected((string) old('setup_required') === '1')>Charge the selected plan's one-time setup fee</option>
            </select>
            <details class="mt-4 text-sm text-foreground">
                <summary class="cursor-pointer">Review all rates, included usage and access discount bands before adoption</summary>
                <div class="mt-3 grid gap-3">
                    @foreach($hybrid['website_plans'] as $candidate)
                        <p><strong>{{ $candidate['name'] }}</strong>: {{ $currency }} {{ number_format($candidate[$currency], 2) }}/month; setup {{ $currency }} {{ number_format($candidate['setup'][$currency], 2) }} once. Includes {{ $candidate['editors'] }} editors, {{ $candidate['files_gib'] }} GiB files, {{ $candidate['database_gib'] }} GiB logical website database and {{ $candidate['bandwidth_gib'] }} GiB application-delivered monthly bandwidth.</p>
                    @endforeach
                    @foreach($hybrid['access'] as $rate)
                        <p>{{ $rate['name'] }}: {{ $currency }} {{ number_format($rate[$currency], 2) }}/distinct active user/month.</p>
                    @endforeach
                    <p>Access discounts are marginal and separate for each category: first 100 full rate, next 400 at 10% off, next 1,000 at 20% off, further users at 30% off. Website, editor and module subscriptions do not receive these discounts. Included website usage is not billed again. No automatic bandwidth or storage overages.</p>
                </div>
            </details>
            <label class="mt-4 flex items-start gap-3 text-sm text-foreground">
                <input type="checkbox" name="confirm_adoption" value="1" required>
                <span>I confirm that this customer explicitly adopts the selected hybrid plan, module subscriptions, editor seats, usage rates and access charges from the effective date, and that existing recurring provider subscriptions and outstanding legacy checkout sessions have been reconciled.</span>
            </label>
            <button type="submit" class="kt-btn kt-btn-primary mt-4">Confirm adoption and schedule agreement</button>
        </form>

        <div class="kt-card p-6">
            <h2 class="text-lg font-semibold text-foreground">Website capacity readiness</h2>
            <p class="mt-2 text-sm text-muted-foreground">Prepare file ownership before adoption, then audit at or after the agreement starts. The scheduled daily audit runs at 03:00; uploads fail safely until a current audit. Legacy files on shared public disks must first be migrated to tenant-scoped storage. Deletions and account recovery remain available over capacity.</p>
            @foreach($capacityCounters as $counter)
                <p class="mt-2 text-sm text-foreground">{{ ucfirst($counter->meter) }} ({{ $counter->period }}): {{ number_format($counter->bytes / 1073741824, 4) }} GiB{{ $counter->audited_at ? ' — audited '.$counter->audited_at->format('Y-m-d H:i') : '' }}.</p>
            @endforeach
            <form method="post" action="{{ route('tenants.billing-capacity.audit', $tenant->uuid) }}" class="mt-4">
                @csrf
                <button type="submit" class="kt-btn kt-btn-outline" @disabled($agreements->isEmpty())>Audit website file storage</button>
            </form>
        </div>

        <div class="kt-card p-6">
            <h2 class="text-lg font-semibold text-foreground">Member billing access</h2>
            <p class="mt-2 text-sm text-muted-foreground">Access licensing is an additional restriction. Assigning a level does not grant role permissions. Prepare assignments before adoption; legacy behavior continues until the agreement starts.</p>
            <div class="mt-4 grid gap-4">
                @forelse($members as $member)
                    <form method="post" action="{{ route('tenants.billing-access.update', [$tenant->uuid, $member]) }}" class="flex flex-wrap items-center gap-3 rounded-lg border border-border p-4">
                        @csrf
                        @method('PUT')
                        <label for="access-{{ $member->id }}" class="text-sm text-foreground">{{ $member->first_name }} {{ $member->last_name }} ({{ $member->email }})</label>
                        <select id="access-{{ $member->id }}" name="level" class="kt-select" required>
                            <option value="unassigned" @selected(!isset($accessLevels[$member->id]))>Unassigned — block workspace access</option>
                            @foreach(['website_editor' => 'Website editor (uses an editor seat)', 'self_service' => 'Self-service', 'operational' => 'Operational'] as $level => $label)
                                <option value="{{ $level }}" @selected(($accessLevels[$member->id] ?? 'none') === $level)>{{ $label }}@if(isset($hybrid['access'][$level])) — {{ $currency }} {{ number_format($hybrid['access'][$level][$currency], 2) }}/active user/month before marginal discounts @endif</option>
                            @endforeach
                        </select>
                        <button type="submit" class="kt-btn kt-btn-outline">Save access for {{ $member->first_name }}</button>
                    </form>
                @empty
                    <p class="text-sm text-muted-foreground">This tenant has no members to assign.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
