@extends('layouts.metronic.app')

@section('title', 'Welcome to Your Organization')

@section('content')
    @php
        $routePrefix = request()->route('subdomain') ? 'tenant.onboarding.' : 'onboarding.';
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <p class="text-xs uppercase tracking-wide text-muted-foreground">Onboarding</p>
            <h1 class="mt-2 text-2xl font-semibold text-foreground">Welcome to {{ $tenant->name }}!</h1>
            <p class="mt-2 text-sm text-muted-foreground">Set your organization branding now or continue directly to your dashboard.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-primary/30 bg-primary/[0.08] p-6">
                <h2 class="text-lg font-semibold text-foreground">Quick Branding</h2>
                <p class="mt-1 text-sm text-muted-foreground">Apply identity settings before your team starts using the workspace.</p>

                <form action="{{ route($routePrefix . 'branding.update') }}" method="POST" enctype="multipart/form-data" class="mt-5 grid gap-4">
                    @csrf
                    <div class="grid gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Organization Name</label>
                        <input class="kt-input" type="text" name="name" value="{{ $tenant->name }}" required />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Logo</label>
                        <input class="kt-input" type="file" name="logo" accept="image/*" />
                    </div>
                    <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">Update & Continue</button>
                </form>
            </div>

            <div class="rounded-xl border border-success/30 bg-success/[0.08] p-6">
                <h2 class="text-lg font-semibold text-foreground">Go to Dashboard</h2>
                <p class="mt-1 text-sm text-muted-foreground">Skip setup for now. You can update branding and configuration later.</p>

                <form action="{{ route($routePrefix . 'finish') }}" method="POST" class="mt-5">
                    @csrf
                    <button type="submit" class="kt-btn kt-btn-success w-full justify-center">Start Exploring</button>
                </form>
            </div>
        </div>

        <div class="rounded-xl border border-dashed border-border bg-background p-4 text-sm text-muted-foreground">
            You can always manage these settings from Organization Settings.
        </div>
    </section>
@endsection
