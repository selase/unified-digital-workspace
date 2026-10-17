@extends('layouts.metronic.app')

@section('title', __('Tenant Dashboard'))

@section('content')
    @php
        $completedChecklistItems = collect($checklist)->filter()->count();
        $checklistProgress = (int) round(($completedChecklistItems / max(1, count($checklist))) * 100);
        $subdomain = request()->route('subdomain') ?? $tenant->slug;
    @endphp

    <div class="flex flex-col gap-6">
        {{-- Page Header --}}
        <x-metronic.page-header
            title="Welcome to {{ $tenant->name }}!"
            description="Institutional Executive Command Center — Real-time telemetry, cross-module governance, and operations radar."
        >
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <span class="kt-badge kt-badge-primary kt-badge-outline gap-1.5 px-3 py-1 text-xs">
                        <span class="size-2 rounded-full bg-primary animate-pulse"></span>
                        {{ $tenant->plan ?? 'Enterprise Tier' }}
                    </span>

                    <a href="{{ route('tenant.users.index', ['subdomain' => $subdomain]) }}" class="kt-btn kt-btn-outline kt-btn-sm">
                        <i class="ki-filled ki-people text-sm"></i>
                        Staff Directory
                    </a>

                    <a href="{{ route('tenant.settings.index', ['subdomain' => $subdomain]) }}" class="kt-btn kt-btn-outline kt-btn-sm">
                        <i class="ki-filled ki-setting-2 text-sm"></i>
                        Workspace Settings
                    </a>
                </div>
            </x-slot:actions>
        </x-metronic.page-header>

        {{-- Executive KPI Ribbon: 5 High-Impact Metrics --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Workforce</span>
                    <span class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                        <i class="ki-filled ki-people text-base"></i>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-foreground">{{ $executiveKpis['staff_count'] }}</span>
                    <span class="text-[11px] text-muted-foreground">Active Staff</span>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Pending Action</span>
                    <span class="size-8 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                        <i class="ki-filled ki-time text-base"></i>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $executiveKpis['pending_governance'] }}</span>
                    <span class="text-[11px] text-amber-600 dark:text-amber-400 font-medium">Reviews & Alerts</span>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Controlled Assets</span>
                    <span class="size-8 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                        <i class="ki-filled ki-folder text-base"></i>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-foreground">{{ $executiveKpis['controlled_assets'] }}</span>
                    <span class="text-[11px] text-muted-foreground">Docs & CMS</span>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Active Ops</span>
                    <span class="size-8 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <i class="ki-filled ki-chart-line-star text-base"></i>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $executiveKpis['active_operations'] }}</span>
                    <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">Projects & Plans</span>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Workspaces</span>
                    <span class="size-8 rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                        <i class="ki-filled ki-element-plus text-base"></i>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-foreground">{{ $executiveKpis['enabled_modules_count'] }}</span>
                    <span class="text-[11px] text-muted-foreground">Active Modules</span>
                </div>
            </div>
        </div>

        {{-- Main Command Center Grid: 8 Cols (Ops Radar + Action Queue) / 4 Cols (Checklist + Usages + Org Profile) --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {{-- Primary Column: Operations Radar & Action Items --}}
            <div class="lg:col-span-8 flex flex-col gap-6">
                {{-- Operations Radar --}}
                <div class="rounded-xl border border-border bg-card p-6 shadow-xs">
                    <div class="flex items-center justify-between pb-4 border-b border-border/60">
                        <div>
                            <h3 class="text-base font-bold text-foreground flex items-center gap-2">
                                <i class="ki-filled ki-radar text-primary text-lg"></i>
                                Multi-Module Operations Radar
                            </h3>
                            <p class="text-xs text-muted-foreground mt-0.5">Real-time status and operational counters across institutional modules</p>
                        </div>
                        <span class="text-xs font-medium text-muted-foreground">
                            {{ count($moduleCards) }} Modules Online
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-5">
                        @forelse($moduleCards as $key => $card)
                            <div class="rounded-xl border border-border/80 bg-background/50 hover:bg-muted/20 hover:border-primary/40 transition-all p-4 flex flex-col justify-between">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="size-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                            <i class="ki-filled {{ $card['icon'] }} text-xl"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-semibold text-foreground">{{ $card['name'] }}</h4>
                                            <span class="text-[11px] text-muted-foreground font-medium">
                                                {{ $card['badge'] }}
                                            </span>
                                        </div>
                                    </div>
                                    <a href="{{ $card['route'] }}" class="kt-btn kt-btn-xs kt-btn-outline gap-1 text-xs">
                                        Open
                                        <i class="ki-filled ki-arrow-right text-[10px]"></i>
                                    </a>
                                </div>

                                <div class="grid grid-cols-3 gap-2 pt-3 mt-3 border-t border-border/50 text-center">
                                    @foreach($card['kpis'] as $kpi)
                                        <div class="rounded-lg bg-muted/40 p-2">
                                            <div class="text-[10px] uppercase font-semibold text-muted-foreground truncate">{{ $kpi['label'] }}</div>
                                            <div class="text-sm font-bold text-foreground mt-0.5">{{ $kpi['value'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="col-span-2 rounded-xl border border-dashed border-border p-8 text-center">
                                <i class="ki-filled ki-element-11 text-3xl text-muted-foreground"></i>
                                <h4 class="mt-2 text-sm font-semibold text-foreground">No institutional modules activated</h4>
                                <p class="text-xs text-muted-foreground mt-1">Contact your system administrator to enable workspace modules for your organization.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Executive Governance & Attention Queue --}}
                <div class="rounded-xl border border-border bg-card p-6 shadow-xs">
                    <div class="flex items-center justify-between pb-4 border-b border-border/60">
                        <div>
                            <h3 class="text-base font-bold text-foreground flex items-center gap-2">
                                <i class="ki-filled ki-notification-status text-amber-500 text-lg"></i>
                                Priority Leadership Attention Queue
                            </h3>
                            <p class="text-xs text-muted-foreground mt-0.5">Critical items requiring management review, authorization, or corrective action</p>
                        </div>
                        <span class="kt-badge kt-badge-outline text-xs">
                            {{ count($actionItems) }} Pending
                        </span>
                    </div>

                    <div class="mt-4 divide-y divide-border/60">
                        @forelse($actionItems as $item)
                            <div class="py-3.5 flex items-center justify-between gap-4 first:pt-1 last:pb-1">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="shrink-0 size-8 rounded-lg flex items-center justify-center text-xs font-bold
                                        @if($item['badge'] === 'danger') bg-rose-500/10 text-rose-600 dark:text-rose-400
                                        @elseif($item['badge'] === 'warning') bg-amber-500/10 text-amber-600 dark:text-amber-400
                                        @else bg-primary/10 text-primary @endif">
                                        @if($item['badge'] === 'danger')
                                            <i class="ki-filled ki-shield-cross text-sm"></i>
                                        @elseif($item['badge'] === 'warning')
                                            <i class="ki-filled ki-information-2 text-sm"></i>
                                        @else
                                            <i class="ki-filled ki-document text-sm"></i>
                                        @endif
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-semibold text-foreground truncate">{{ $item['title'] }}</span>
                                            <span class="text-[10px] px-1.5 py-0.5 rounded font-mono bg-muted text-muted-foreground shrink-0">{{ $item['reference'] }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-[11px] text-muted-foreground mt-0.5">
                                            <span>{{ $item['module'] }}</span>
                                            <span>•</span>
                                            <span>{{ $item['timestamp'] }}</span>
                                            <span>•</span>
                                            <span class="capitalize font-medium">{{ str_replace('_', ' ', $item['status']) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <a href="{{ $item['url'] }}" class="kt-btn kt-btn-xs kt-btn-outline shrink-0">
                                    Review
                                </a>
                            </div>
                        @empty
                            <div class="py-8 text-center">
                                <div class="size-10 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto">
                                    <i class="ki-filled ki-check-circle text-xl"></i>
                                </div>
                                <h4 class="mt-2 text-sm font-semibold text-foreground">All Governance Channels Clear</h4>
                                <p class="text-xs text-muted-foreground mt-1">There are no outstanding urgent incidents, open quality alerts, or pending memos requiring immediate executive action.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Secondary Column: Onboarding Checklist + Metered Usages + Tenant Info --}}
            <div class="lg:col-span-4 flex flex-col gap-6">
                {{-- Onboarding & Readiness Widget --}}
                @if(!$checklist['onboarding'])
                    <div class="rounded-xl border border-border bg-card p-6 shadow-xs" id="kt_getting_started_widget">
                        <div class="flex items-center justify-between pb-3 border-b border-border/60">
                            <div>
                                <h3 class="text-sm font-bold text-foreground">Getting Started</h3>
                                <p class="text-xs text-muted-foreground">Complete setup to verify your workspace</p>
                            </div>
                            <span class="text-xs font-bold text-primary">{{ $checklistProgress }}%</span>
                        </div>

                        <div class="mt-4 space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="size-8 rounded-full flex items-center justify-center text-xs font-bold {{ $checklist['branding'] ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-primary/10 text-primary' }}">
                                    @if ($checklist['branding'])
                                        <i class="ki-filled ki-check"></i>
                                    @else
                                        1
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('tenant.settings.index', ['subdomain' => $subdomain]) }}"
                                        class="text-xs font-semibold {{ $checklist['branding'] ? 'line-through text-muted-foreground' : 'text-foreground hover:text-primary' }}">
                                        Customize Branding
                                    </a>
                                    <span class="text-[11px] text-muted-foreground block">Logo & primary color theme</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="size-8 rounded-full flex items-center justify-center text-xs font-bold {{ $checklist['team'] ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-primary/10 text-primary' }}">
                                    @if ($checklist['team'])
                                        <i class="ki-filled ki-check"></i>
                                    @else
                                        2
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('tenant.users.index', ['subdomain' => $subdomain]) }}"
                                        class="text-xs font-semibold {{ $checklist['team'] ? 'line-through text-muted-foreground' : 'text-foreground hover:text-primary' }}">
                                        Add Your Team
                                    </a>
                                    <span class="text-[11px] text-muted-foreground block">Invite managers & employees</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="size-8 rounded-full flex items-center justify-center text-xs font-bold {{ $checklist['onboarding'] ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-muted text-muted-foreground' }}">
                                    3
                                </div>
                                <div class="min-w-0 flex-1">
                                    <form action="{{ route(request()->route('subdomain') ? 'tenant.onboarding.finish' : 'onboarding.finish', ['subdomain' => $subdomain]) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-primary hover:underline text-left">
                                            Mark as Setup Complete
                                        </button>
                                    </form>
                                    <span class="text-[11px] text-muted-foreground block">Dismiss this onboarding guide</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Metered Plan Features & Consumption --}}
                <div class="rounded-xl border border-border bg-card p-6 shadow-xs">
                    <div class="flex items-center justify-between pb-3 border-b border-border/60">
                        <div>
                            <h3 class="text-sm font-bold text-foreground">Plan Quotas & Limits</h3>
                            <p class="text-xs text-muted-foreground">Metered feature usage against active subscription</p>
                        </div>
                        <a href="{{ route('tenant.pricing', ['subdomain' => $subdomain]) }}" class="text-[11px] font-semibold text-primary hover:underline">
                            Upgrade
                        </a>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div class="rounded-lg bg-muted/30 p-3 flex items-center justify-between">
                            <span class="text-xs text-muted-foreground">Connected Tenants</span>
                            <span class="text-sm font-bold text-foreground">{{ auth()->user()->tenants()->count() }}</span>
                        </div>

                        @forelse($usages as $usage)
                            @php
                                $percent = ($usage['used'] / max(1, $usage['limit'])) * 100;
                            @endphp
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-medium text-foreground">{{ ucfirst(str_replace('_', ' ', $usage['slug'])) }}</span>
                                    <span class="text-muted-foreground font-mono">{{ $usage['used'] }} / {{ $usage['limit'] }}</span>
                                </div>
                                <div class="h-2 rounded-full bg-muted overflow-hidden">
                                    <div class="h-2 rounded-full {{ $percent > 90 ? 'bg-rose-500' : ($percent > 70 ? 'bg-amber-500' : 'bg-primary') }}"
                                        style="width: {{ min(100, $percent) }}%"></div>
                                </div>
                                <div class="text-[10px] text-muted-foreground text-right font-medium">
                                    {{ number_format($percent, 1) }}% Consumed
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg border border-dashed border-border p-4 text-center">
                                <i class="ki-filled ki-chart-line text-xl text-muted-foreground"></i>
                                <div class="text-xs font-semibold text-foreground mt-1">Unlimited Core Resources</div>
                                <div class="text-[11px] text-muted-foreground mt-0.5">No hard limits are currently enforced on this tier.</div>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Institutional Profile & Quick Access --}}
                <div class="rounded-xl border border-border bg-card p-6 shadow-xs">
                    <h3 class="text-sm font-bold text-foreground pb-3 border-b border-border/60">
                        Organization Identity
                    </h3>

                    <div class="mt-4 space-y-3 text-xs">
                        <div class="flex justify-between items-center py-1">
                            <span class="text-muted-foreground">Organization Name</span>
                            <span class="font-semibold text-foreground">{{ $tenant->name }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1">
                            <span class="text-muted-foreground">Subdomain</span>
                            <span class="font-mono text-foreground">{{ $tenant->slug }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1">
                            <span class="text-muted-foreground">Status</span>
                            <span class="kt-badge kt-badge-success kt-badge-outline text-[11px]">Active</span>
                        </div>
                        <div class="flex justify-between items-center py-1">
                            <span class="text-muted-foreground">Created</span>
                            <span class="text-foreground">{{ $tenant->created_at?->format('d/m/Y') ?? 'N/A' }}</span>
                        </div>
                    </div>

                    <div class="mt-5 pt-4 border-t border-border/60 grid grid-cols-2 gap-2">
                        <a href="{{ route('tenant.roles.index', ['subdomain' => $subdomain]) }}" class="kt-btn kt-btn-xs kt-btn-outline w-full justify-center">
                            Roles & Perms
                        </a>
                        <a href="{{ route('tenant.settings.billing', ['subdomain' => $subdomain]) }}" class="kt-btn kt-btn-xs kt-btn-outline w-full justify-center">
                            Billing Portal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
