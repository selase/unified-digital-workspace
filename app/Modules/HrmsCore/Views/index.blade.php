@extends('layouts.metronic.app')

@section('title', 'HRMS Hub')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">HRMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">HRMS Hub</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Entry point for people operations, leave, appraisals, and recruitment APIs.</p>
                </div>
                <a href="{{ $rootApiUrl }}" class="kt-btn kt-btn-primary">Open API Root</a>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Module Version</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $moduleVersion }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Feature Flags</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $featureCount }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Permission Scopes</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $permissionCount }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-foreground">Key API Resources</h2>
                <span class="text-xs text-muted-foreground">Versioned endpoints</span>
            </div>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($apiLinks as $apiLink)
                    <a href="{{ $apiLink['url'] }}" class="rounded-lg border border-border p-4 transition hover:border-primary/40 hover:bg-muted/20">
                        <p class="font-medium text-foreground">{{ $apiLink['label'] }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ $apiLink['route'] }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endsection
