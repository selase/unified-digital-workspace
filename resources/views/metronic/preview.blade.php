@extends('layouts.metronic.app')

@section('title', 'Metronic UI Preview')

@section('content')
    @php
        $modulePages = config('metronic.module_pages', []);
    @endphp

    <section class="grid gap-6">
        <div class="rounded-2xl border border-border bg-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Track 14 Kickoff</p>
                    <h1 class="mt-2 text-3xl font-semibold text-foreground">Metronic UI Preview</h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Demo {{ config('metronic.demo') }} mapping and layout primitives for UDW modules.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a class="kt-btn kt-btn-outline" href="{{ route('metronic.assets', ['path' => 'css/styles.css']) }}">Stylesheet</a>
                    <a class="kt-btn kt-btn-primary" href="{{ route('metronic.assets', ['path' => 'js/core.bundle.js']) }}">Core JS</a>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <article class="rounded-2xl border border-border bg-card p-6">
                <h2 class="text-lg font-semibold text-foreground">Layout Tokens</h2>
                <div class="mt-4 grid gap-2 text-sm text-muted-foreground">
                    <div class="flex items-center justify-between">
                        <span>Header Height</span>
                        <span class="font-medium text-foreground">64px</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Sidebar Width</span>
                        <span class="font-medium text-foreground">270px</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Theme</span>
                        <span class="font-medium text-foreground">Light (toggle-ready)</span>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-border bg-card p-6">
                <h2 class="text-lg font-semibold text-foreground">Navigation Strategy</h2>
                <p class="mt-3 text-sm text-muted-foreground">
                    Module entries are mapped to Metronic demo pages and live routes when available.
                </p>
                <ul class="mt-4 grid gap-2 text-sm text-muted-foreground">
                    <li>Fallback to demo page names when routes are pending.</li>
                    <li>Tenant-aware routes resolve using the active tenant slug.</li>
                    <li>Links remain disabled until modules are wired.</li>
                </ul>
            </article>

            <article class="rounded-2xl border border-border bg-card p-6">
                <h2 class="text-lg font-semibold text-foreground">Next UI Steps</h2>
                <div class="mt-4 grid gap-2 text-sm text-muted-foreground">
                    <p>1. Replace admin/tenant master layouts with Metronic equivalents.</p>
                    <p>2. Extract shared components (buttons, tables, forms).</p>
                    <p>3. Apply responsive audit on HRMS, Docs, Memos, Incidents.</p>
                </div>
            </article>
        </div>

        <div class="rounded-2xl border border-border bg-card p-6">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-lg font-semibold text-foreground">Module Demo Map</h2>
                <span class="text-xs text-muted-foreground">{{ count($modulePages) }} modules tracked</span>
            </div>
            <div class="mt-4 grid gap-3">
                @foreach($modulePages as $moduleKey => $module)
                    <div class="rounded-xl border border-border/70 bg-background p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="font-medium text-foreground">{{ $module['label'] }}</div>
                            <div class="text-xs text-muted-foreground">Module key: {{ $moduleKey }}</div>
                        </div>
                        <div class="text-sm text-muted-foreground">
                            Demo page: <span class="font-medium text-foreground">{{ $module['demo_page'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
