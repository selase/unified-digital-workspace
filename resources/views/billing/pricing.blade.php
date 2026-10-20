@extends('layouts.metronic.app')

@section('title', 'Pricing Plans')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8 text-center">
            <p class="text-xs uppercase tracking-wide text-muted-foreground">Billing</p>
            <h1 class="mt-2 text-3xl font-semibold text-foreground">Choose Your Plan</h1>
            <p class="mt-3 text-sm text-muted-foreground">
                Direct plan changes are available before usage is recorded in the current month. After usage begins, contact an administrator to arrange a change for the next billing period. Initial plan setup is still available.
            </p>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            @foreach($packages as $package)
                @php
                    $currency = $tenant->billingCurrency();
                    $configuredPrice = $currency === 'GHS' ? $package->price_ghs : $package->price;
                @endphp
                <article class="rounded-xl border {{ $currentPackage?->id === $package->id ? 'border-primary' : 'border-border' }} bg-background p-6">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="text-xl font-semibold text-foreground">{{ $package->name }}</h2>
                        @if($currentPackage?->id === $package->id)
                            <span class="kt-badge kt-badge-primary">Current Plan</span>
                        @endif
                    </div>

                    <p class="mt-3 min-h-10 text-sm text-muted-foreground">{{ $package->description }}</p>

                    <div class="mt-6 flex items-end gap-2">
                        <span class="text-lg text-muted-foreground">{{ $currency }}</span>
                        <span class="text-4xl font-semibold text-foreground">{{ $configuredPrice === null ? 'Not configured' : number_format($configuredPrice, 2) }}</span>
                        <span class="pb-1 text-sm text-muted-foreground">/ {{ $package->interval }}</span>
                    </div>

                    <ul class="mt-6 space-y-3">
                        @foreach($package->features as $feature)
                            <li class="flex items-start gap-2 text-sm text-foreground">
                                <i class="ki-filled ki-check-circle text-success mt-0.5"></i>
                                <span>{{ $feature->name }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-8">
                        @if($currentPackage?->id === $package->id)
                            <button class="kt-btn kt-btn-outline w-full" disabled>Active</button>
                        @else
                            <form action="{{ route('tenant.billing.checkout') }}" method="POST">
                                @csrf
                                <input type="hidden" name="plan" value="{{ $package->slug }}">
                                <button type="submit" class="kt-btn kt-btn-primary w-full" @disabled($configuredPrice === null)>Upgrade to {{ $package->name }}</button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endsection
