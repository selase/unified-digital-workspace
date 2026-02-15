@extends('layouts.metronic.app')

@section('title', __('Tenant Dashboard'))

@section('content')
    @php
        $completedChecklistItems = collect($checklist)->filter()->count();
        $checklistProgress = (int) round(($completedChecklistItems / max(1, count($checklist))) * 100);
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="text-center">
                <h3 class="text-2xl font-semibold text-foreground">
                    Welcome to {{ app(\App\Services\Tenancy\TenantContext::class)->getTenant()->name }}!
                </h3>
                <p class="mt-2 text-sm text-muted-foreground">This is your tenant-specific dashboard, accessed via subdomain.</p>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-border bg-muted/30 p-4 text-start">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Onboarding Progress</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $checklistProgress }}%</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4 text-start">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Checklist Completed</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $completedChecklistItems }} / {{ count($checklist) }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4 text-start">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Usage Metrics</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ count($usages) }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-12">
            @if(!$checklist['onboarding'])
                <div class="lg:col-span-4">
                    <div class="rounded-xl border border-border bg-background p-6 h-full" id="kt_getting_started_widget">
                        <div>
                            <h3 class="text-sm font-semibold uppercase text-foreground">Getting Started</h3>
                            <p class="text-xs text-muted-foreground">Complete these to set up your org</p>
                        </div>
                        <div class="mt-4 space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="size-8 rounded-full flex items-center justify-center text-sm font-semibold {{ $checklist['branding'] ? 'bg-green-50 text-green-600' : 'bg-primary/10 text-primary' }}">
                                    @if ($checklist['branding'])
                                        <i class="ki-filled ki-check"></i>
                                    @else
                                        1
                                    @endif
                                </div>
                                <div>
                                    <a href="{{ route('tenant.settings.index') }}"
                                        class="text-sm font-semibold {{ $checklist['branding'] ? 'line-through text-muted-foreground' : 'text-foreground' }}">
                                        Customize Branding
                                    </a>
                                    <span class="text-xs text-muted-foreground block">Logo & primary color</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="size-8 rounded-full flex items-center justify-center text-sm font-semibold {{ $checklist['team'] ? 'bg-green-50 text-green-600' : 'bg-primary/10 text-primary' }}">
                                    @if ($checklist['team'])
                                        <i class="ki-filled ki-check"></i>
                                    @else
                                        2
                                    @endif
                                </div>
                                <div>
                                    <a href="{{ route('tenant.users.index') }}"
                                        class="text-sm font-semibold {{ $checklist['team'] ? 'line-through text-muted-foreground' : 'text-foreground' }}">
                                        Add Your Team
                                    </a>
                                    <span class="text-xs text-muted-foreground block">Invite your colleagues</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="size-8 rounded-full flex items-center justify-center text-sm font-semibold {{ $checklist['onboarding'] ? 'bg-green-50 text-green-600' : 'bg-primary/10 text-primary' }}">
                                    3
                                </div>
                                <div>
                                    <form action="{{ route(request()->route('subdomain') ? 'tenant.onboarding.finish' : 'onboarding.finish') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="text-sm font-semibold text-foreground">Mark as Setup Complete</button>
                                    </form>
                                    <span class="text-xs text-muted-foreground block">Dismiss this checklist</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="{{ $checklist['onboarding'] ? 'lg:col-span-12' : 'lg:col-span-8' }}">
                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-border bg-background p-5">
                        <div class="text-xs text-muted-foreground">Connected Tenants</div>
                        <div class="mt-2 text-2xl font-semibold text-foreground">
                            {{ auth()->user()->tenants()->count() }}
                        </div>
                    </div>

                    @forelse($usages as $usage)
                        <div class="rounded-xl border border-border bg-background p-5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-muted-foreground">{{ ucfirst(str_replace('_', ' ', $usage['slug'])) }} Usage</span>
                                <span class="text-xs text-muted-foreground">{{ $usage['used'] }}/{{ $usage['limit'] }}</span>
                            </div>
                            <div class="mt-3 text-xl font-semibold text-foreground">{{ $usage['used'] }}</div>
                            <div class="mt-3 h-2 rounded-full bg-muted">
                                <div class="h-2 rounded-full bg-primary"
                                    style="width: {{ ($usage['used'] / max(1, $usage['limit'])) * 100 }}%"></div>
                            </div>
                            <div class="mt-2 text-xs text-muted-foreground">
                                {{ number_format(($usage['used'] / max(1, $usage['limit'])) * 100, 1) }}% Consumed
                            </div>
                        </div>
                    @empty
                        @if($checklist['onboarding'])
                            <div class="rounded-xl border border-dashed border-border bg-muted/30 p-6 flex flex-col items-center justify-center text-center md:col-span-2 xl:col-span-4">
                                <i class="ki-filled ki-chart-line text-3xl text-muted-foreground"></i>
                                <h3 class="mt-4 text-lg font-semibold text-foreground">No active usage tracked</h3>
                                <p class="text-sm text-muted-foreground">Enable metered features in your plan to see consumption here.</p>
                            </div>
                        @endif
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection
