@extends('layouts.metronic.app')

@section('title', __('locale.menu.dashboard'))

@section('content')
    <section class="grid gap-6">
        @if(session('onboarding_just_completed'))
            <div class="rounded-xl border border-primary/10 bg-primary/10 p-5 flex flex-wrap items-start gap-4" data-banner>
                <span class="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none">
                        <path opacity="0.3"
                            d="M12 22C13.6569 22 15 20.6569 15 19C15 17.3431 13.6569 16 12 16C10.3431 16 9 17.3431 9 19C9 20.6569 10.3431 22 12 22Z"
                            fill="currentColor"></path>
                        <path
                            d="M19 15V18C19 18.6 18.6 19 18 19H6C5.4 19 5 18.6 5 18V15C5 14.4 5.4 14 6 14H18C18.6 14 19 14.4 19 15ZM12 12C14.2091 12 16 10.2091 16 8C16 5.79086 14.2091 4 12 4C9.79086 4 8 5.79086 8 8C8 10.2091 9.79086 12 12 12Z"
                            fill="currentColor"></path>
                    </svg>
                </span>
                <div class="flex-1">
                    <h5 class="text-base font-semibold text-foreground">Welcome to your new organization!</h5>
                    <p class="text-sm text-muted-foreground">
                        You've successfully completed onboarding. Here's your dashboard where you can manage users,
                        track analytics, and more.
                    </p>
                </div>
                <button type="button" class="kt-btn kt-btn-sm kt-btn-ghost" onclick="this.closest('[data-banner]').remove()">
                    Dismiss
                </button>
            </div>
        @endif

        @php
            $currentTenant = app(\App\Services\Tenancy\TenantContext::class)->getTenant();
        @endphp

        @if($currentTenant && !$currentTenant->onboarding_completed_at && !session('onboarding_just_completed'))
            <div class="rounded-xl border border-yellow-500 bg-yellow-50 p-5 flex flex-wrap items-start gap-4" data-banner>
                <span class="flex size-10 items-center justify-center rounded-full bg-yellow-500 text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.3" x="2" y="2" width="20" height="20" rx="10" fill="currentColor"></rect>
                        <rect x="11" y="14" width="7" height="2" rx="1" transform="rotate(-90 11 14)" fill="currentColor"></rect>
                        <rect x="11" y="17" width="2" height="2" rx="1" transform="rotate(-90 11 17)" fill="currentColor"></rect>
                    </svg>
                </span>
                <div class="flex-1">
                    <h5 class="text-base font-semibold text-yellow-600">Finish Setting Up Your Organization</h5>
                    <p class="text-sm text-muted-foreground">
                        You have some pending steps in the setup wizard.
                        <a href="{{ request()->route('subdomain') ? route('tenant.onboarding.wizard') : route('onboarding.wizard') }}"
                            class="font-semibold text-yellow-600 underline">Click here to complete it now.</a>
                    </p>
                </div>
                <button type="button" class="kt-btn kt-btn-sm kt-btn-ghost" onclick="this.closest('[data-banner]').remove()">
                    Dismiss
                </button>
            </div>
        @endif

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            @can('user analytics')
                <div class="rounded-xl border border-border bg-background p-5">
                    <div class="flex items-center justify-between">
                        <span class="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none">
                                <path
                                    d="M16.0173 9H15.3945C14.2833 9 13.263 9.61425 12.7431 10.5963L12.154 11.7091C12.0645 11.8781 12.1072 12.0868 12.2559 12.2071L12.6402 12.5183C13.2631 13.0225 13.7556 13.6691 14.0764 14.4035L14.2321 14.7601C14.2957 14.9058 14.4396 15 14.5987 15H18.6747C19.7297 15 20.4057 13.8774 19.912 12.945L18.6686 10.5963C18.1487 9.61425 17.1285 9 16.0173 9Z"
                                    fill="currentColor" />
                                <rect opacity="0.3" x="14" y="4" width="4" height="4" rx="2" fill="currentColor" />
                                <path
                                    d="M4.65486 14.8559C5.40389 13.1224 7.11161 12 9 12C10.8884 12 12.5961 13.1224 13.3451 14.8559L14.793 18.2067C15.3636 19.5271 14.3955 21 12.9571 21H5.04292C3.60453 21 2.63644 19.5271 3.20698 18.2067L4.65486 14.8559Z"
                                    fill="currentColor" />
                                <rect opacity="0.3" x="6" y="5" width="6" height="6" rx="3" fill="currentColor" />
                            </svg>
                        </span>
                        <span class="text-xs text-muted-foreground">Users</span>
                    </div>
                    <div class="mt-4 text-2xl font-semibold text-foreground" data-kt-tooltip="true"
                        data-kt-tooltip-placement="top" title="Monitor your organization's growth here.">
                        {{ \App\Models\User::count() }}
                    </div>
                    <div class="text-sm text-muted-foreground">{{ __('locale.menu.users') }}</div>
                </div>
            @endcan

            @can('read audit-trail')
                <div class="rounded-xl border border-border bg-background p-5">
                    <div class="flex items-center justify-between">
                        <span class="flex size-10 items-center justify-center rounded-full bg-violet-50 text-violet-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none">
                                <path opacity="0.3"
                                    d="M18 21.6C16.3 21.6 15 20.3 15 18.6V2.50001C15 2.20001 14.6 1.99996 14.3 2.19996L13 3.59999L11.7 2.3C11.3 1.9 10.7 1.9 10.3 2.3L9 3.59999L7.70001 2.3C7.30001 1.9 6.69999 1.9 6.29999 2.3L5 3.59999L3.70001 2.3C3.50001 2.1 3 2.20001 3 3.50001V18.6C3 20.3 4.3 21.6 6 21.6H18Z"
                                    fill="currentColor"></path>
                                <path
                                    d="M12 12.6H11C10.4 12.6 10 12.2 10 11.6C10 11 10.4 10.6 11 10.6H12C12.6 10.6 13 11 13 11.6C13 12.2 12.6 12.6 12 12.6ZM9 11.6C9 11 8.6 10.6 8 10.6H6C5.4 10.6 5 11 5 11.6C5 12.2 5.4 12.6 6 12.6H8C8.6 12.6 9 12.2 9 11.6ZM9 7.59998C9 6.99998 8.6 6.59998 8 6.59998H6C5.4 6.59998 5 6.99998 5 7.59998C5 8.19998 5.4 8.59998 6 8.59998H8C8.6 8.59998 9 8.19998 9 7.59998ZM13 7.59998C13 6.99998 12.6 6.59998 12 6.59998H11C10.4 6.59998 10 6.99998 10 7.59998C10 8.19998 10.4 8.59998 11 8.59998H12C12.6 8.59998 13 8.19998 13 7.59998ZM13 15.6C13 15 12.6 14.6 12 14.6H10C9.4 14.6 9 15 9 15.6C9 16.2 9.4 16.6 10 16.6H12C12.6 16.6 13 16.2 13 15.6Z"
                                    fill="currentColor"></path>
                                <path
                                    d="M15 18.6C15 20.3 16.3 21.6 18 21.6C19.7 21.6 21 20.3 21 18.6V12.5C21 12.2 20.6 12 20.3 12.2L19 13.6L17.7 12.3C17.3 11.9 16.7 11.9 16.3 12.3L15 13.6V18.6Z"
                                    fill="currentColor"></path>
                            </svg>
                        </span>
                        <span class="text-xs text-muted-foreground">Activity</span>
                    </div>
                    <div class="mt-4 text-2xl font-semibold text-foreground">+{{ \Spatie\Activitylog\Models\Activity::count() }}</div>
                    <div class="text-sm text-muted-foreground">{{ __('locale.labels.activity_log') }}</div>
                </div>
            @endcan

            <div class="rounded-xl border border-border bg-background p-5">
                <div class="flex items-center justify-between">
                    <span class="flex size-10 items-center justify-center rounded-full bg-yellow-50 text-yellow-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none">
                            <path opacity="0.3"
                                d="M20 15H4C2.9 15 2 14.1 2 13V7C2 6.4 2.4 6 3 6H21C21.6 6 22 6.4 22 7V13C22 14.1 21.1 15 20 15ZM13 12H11C10.5 12 10 12.4 10 13V16C10 16.5 10.4 17 11 17H13C13.6 17 14 16.6 14 16V13C14 12.4 13.6 12 13 12Z"
                                fill="currentColor"></path>
                            <path
                                d="M14 6V5H10V6H8V5C8 3.9 8.9 3 10 3H14C15.1 3 16 3.9 16 5V6H14ZM20 15H14V16C14 16.6 13.5 17 13 17H11C10.5 17 10 16.6 10 16V15H4C3.6 15 3.3 14.9 3 14.7V18C3 19.1 3.9 20 5 20H19C20.1 20 21 19.1 21 18V14.7C20.7 14.9 20.4 15 20 15Z"
                                fill="currentColor"></path>
                        </svg>
                    </span>
                    <span class="text-xs text-muted-foreground">Finance</span>
                </div>
                <div class="mt-4 text-2xl font-semibold text-foreground">$50,000</div>
                <div class="text-sm text-muted-foreground">Milestone Reached</div>
            </div>

            <div class="rounded-xl border border-border bg-background p-5">
                <div class="flex items-center justify-between">
                    <span class="flex size-10 items-center justify-center rounded-full bg-green-50 text-green-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none">
                            <path opacity="0.3"
                                d="M10.9607 12.9128H18.8607C19.4607 12.9128 19.9607 13.4128 19.8607 14.0128C19.2607 19.0128 14.4607 22.7128 9.26068 21.7128C5.66068 21.0128 2.86071 18.2128 2.16071 14.6128C1.16071 9.31284 4.96069 4.61281 9.86069 4.01281C10.4607 3.91281 10.9607 4.41281 10.9607 5.01281V12.9128Z"
                                fill="currentColor"></path>
                            <path
                                d="M12.9607 10.9128V3.01281C12.9607 2.41281 13.4607 1.91281 14.0607 2.01281C16.0607 2.21281 17.8607 3.11284 19.2607 4.61284C20.6607 6.01284 21.5607 7.91285 21.8607 9.81285C21.9607 10.4129 21.4607 10.9128 20.8607 10.9128H12.9607Z"
                                fill="currentColor"></path>
                        </svg>
                    </span>
                    <span class="text-xs text-muted-foreground">Revenue</span>
                </div>
                <div class="mt-4 text-2xl font-semibold text-foreground">$50,000</div>
                <div class="text-sm text-muted-foreground">Milestone Reached</div>
            </div>
        </div>

        @can('user analytics')
            <div class="grid gap-6 lg:grid-cols-12">
                <div class="lg:col-span-8 rounded-xl border border-border bg-background p-6" id="kt_users_trend_widget">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold uppercase text-foreground">{{ strtoupper(__('locale.labels.users_by_role')) }}</h3>
                        <form action="{{ route('dashboard') }}" method="GET">
                            <select name="days" class="kt-select" onchange="this.form.submit()">
                                <option value="7" {{ $days == 7 ? 'selected' : '' }}>Last 7 Days</option>
                                <option value="30" {{ $days == 30 ? 'selected' : '' }}>Last 30 Days</option>
                                <option value="90" {{ $days == 90 ? 'selected' : '' }}>Last 90 Days</option>
                            </select>
                        </form>
                    </div>
                    <div class="mt-4">
                        <x-chart id="users-trend-chart" type="line" :data="[
                            'labels' => $userTrendData['labels'],
                            'datasets' => [
                                [
                                    'label' => 'New Users (Last ' . $days . ' Days)',
                                    'data' => $userTrendData['data'],
                                    'borderColor' => '#009EF7',
                                    'backgroundColor' => 'rgba(0, 158, 247, 0.2)',
                                    'fill' => true,
                                ],
                            ],
                        ]" />
                    </div>
                </div>

                <div class="lg:col-span-4 grid gap-6">
                    <div class="rounded-xl border border-border bg-background p-6" id="kt_getting_started_widget">
                        <h3 class="text-sm font-semibold uppercase text-foreground">Getting Started</h3>
                        <div class="mt-4 space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="size-8 rounded-full flex items-center justify-center text-sm font-semibold {{ $checklist['onboarding'] ? 'bg-green-50 text-green-600' : 'bg-primary/10 text-primary' }}">
                                    @if ($checklist['onboarding'])
                                        <i class="ki-filled ki-check"></i>
                                    @else
                                        1
                                    @endif
                                </div>
                                <div>
                                    <span class="text-sm font-semibold {{ $checklist['onboarding'] ? 'line-through text-muted-foreground' : 'text-foreground' }}">Complete Onboarding</span>
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
                                    <a href="{{ route('users.index') }}"
                                        class="text-sm font-semibold {{ $checklist['team'] ? 'line-through text-muted-foreground' : 'text-foreground' }}">Manage Your Team</a>
                                    <span class="text-xs text-muted-foreground block">Add more colleagues.</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="size-8 rounded-full flex items-center justify-center text-sm font-semibold {{ $checklist['branding'] ? 'bg-green-50 text-green-600' : 'bg-primary/10 text-primary' }}">
                                    @if ($checklist['branding'])
                                        <i class="ki-filled ki-check"></i>
                                    @else
                                        3
                                    @endif
                                </div>
                                <div>
                                    <a href="#"
                                        class="text-sm font-semibold {{ $checklist['branding'] ? 'line-through text-muted-foreground' : 'text-foreground' }}">Refine Branding</a>
                                    <span class="text-xs text-muted-foreground block">Logo & theme.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-border bg-background p-6" id="kt_recent_users_widget">
                        <h3 class="text-sm font-semibold uppercase text-foreground">{{ strtoupper(__('locale.labels.recent_users')) }}</h3>
                        <div class="mt-4 space-y-4">
                            @foreach ($users as $user)
                                <div class="flex items-center gap-3">
                                    <div class="size-10 rounded-full overflow-hidden bg-muted">
                                        @if (!empty($user->photo))
                                            <img src="{{ Storage::disk('local')->url($user->photo) }}" alt="user photo" class="h-full w-full object-cover">
                                        @else
                                            <img src="{{ $user->gravatar }}" alt="user photo" class="h-full w-full object-cover">
                                        @endif
                                    </div>
                                    <div>
                                        <a href="{{ route('users.show', $user->uuid) }}"
                                            class="text-sm font-semibold text-foreground">{{ $user->displayName() }}</a>
                                        <span class="text-xs text-muted-foreground block">
                                            {{ $user->roles()->first()?->name ?? 'N/A' }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endcan

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-border bg-background p-6" id="kt_tenant_growth_widget">
                <h3 class="text-sm font-semibold uppercase text-foreground">Tenant Growth (Last {{ $days }} Days)</h3>
                <div class="mt-4">
                    <x-chart id="tenant-growth-chart" type="line" :data="[
                        'labels' => $tenantGrowthTrend['labels'],
                        'datasets' => [
                            [
                                'label' => 'New Tenants',
                                'data' => $tenantGrowthTrend['data'],
                                'borderColor' => '#50CD89',
                                'backgroundColor' => 'rgba(80, 205, 137, 0.2)',
                                'fill' => true,
                            ],
                        ],
                    ]" />
                </div>
            </div>

            <div class="rounded-xl border border-border bg-background p-6" id="kt_tenant_status_widget">
                <h3 class="text-sm font-semibold uppercase text-foreground">Tenant Status</h3>
                <div class="mt-4">
                    <x-chart id="tenant-status-chart" type="doughnut" :data="[
                        'labels' => $tenantStatusDistribution['labels'],
                        'datasets' => [
                            [
                                'data' => $tenantStatusDistribution['data'],
                                'backgroundColor' => ['#009EF7', '#F1416C', '#FFC700', '#50CD89'],
                            ],
                        ],
                    ]" />
                </div>
            </div>

            <div class="rounded-xl border border-border bg-background p-6" id="kt_top_tenants_widget">
                <h3 class="text-sm font-semibold uppercase text-foreground">Top 5 Tenants (by Users)</h3>
                <div class="mt-4">
                    <x-chart id="top-tenants-chart" type="bar" :data="[
                        'labels' => $topTenantsByUsers['labels'],
                        'datasets' => [
                            [
                                'label' => 'User Count',
                                'data' => $topTenantsByUsers['data'],
                                'backgroundColor' => '#7239EA',
                            ],
                        ],
                    ]" />
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-border bg-background p-6">
                <div>
                    <h3 class="text-sm font-semibold uppercase text-foreground">Login Activity</h3>
                    <span class="text-xs text-muted-foreground">Real-time session telemetry</span>
                </div>
                <div class="mt-4">
                    <x-chart id="login-activity-chart" type="line" :data="[
                        'labels' => $loginTrend['labels'],
                        'datasets' => [
                            [
                                'label' => 'Sessions',
                                'data' => $loginTrend['data'],
                                'borderColor' => '#009EF7',
                                'backgroundColor' => 'rgba(0, 158, 247, 0.1)',
                                'fill' => true,
                                'tension' => 0.4,
                                'pointRadius' => 4,
                            ],
                        ],
                    ]" />
                </div>
            </div>
            <div class="rounded-xl border border-border bg-background p-6">
                <div>
                    <h3 class="text-sm font-semibold uppercase text-foreground">Resource Growth</h3>
                    <span class="text-xs text-muted-foreground">Enterprise feature consumption</span>
                </div>
                <div class="mt-4">
                    <x-chart id="usage-growth-chart" type="bar" :data="[
                        'labels' => $usageGrowth['labels'],
                        'datasets' => [
                            [
                                'label' => 'Units Consumed',
                                'data' => $usageGrowth['data'],
                                'backgroundColor' => ['#50CD89', '#009EF7', '#F1416C', '#FFC700', '#7239EA', '#009EF7', '#50CD89'],
                                'borderRadius' => 5,
                            ],
                        ],
                    ]" />
                </div>
            </div>
        </div>

        @hasrole('Superadmin')
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-border bg-background p-6">
                    <h3 class="text-sm font-semibold uppercase text-foreground">Provisioning Distribution</h3>
                    <div class="mt-4">
                        <x-chart id="isolation-mode-chart" type="pie" :data="[
                            'labels' => $isolationModeDistribution['labels'],
                            'datasets' => [
                                [
                                    'data' => $isolationModeDistribution['data'],
                                    'backgroundColor' => ['#50CD89', '#009EF7', '#F1416C'],
                                ],
                            ],
                        ]" />
                    </div>
                </div>
                <div class="rounded-xl border border-border bg-background p-6">
                    <h3 class="text-sm font-semibold uppercase text-foreground">Infrastructure Overview</h3>
                    <p class="text-xs text-muted-foreground">System-wide resource allocation</p>
                    <div class="mt-4 space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-full bg-green-50 text-green-600 flex items-center justify-center">
                                    <i class="ki-filled ki-data"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-foreground">Shared Pool</div>
                                    <div class="text-xs text-muted-foreground">Multi-tenant PostgreSQL</div>
                                </div>
                            </div>
                            <span class="kt-badge kt-badge-outline kt-badge-success">Healthy</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                                    <i class="ki-filled ki-cloud"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-foreground">Dedicated Clusters</div>
                                    <div class="text-xs text-muted-foreground">Provisioned per-tenant instances</div>
                                </div>
                            </div>
                            <span class="kt-badge kt-badge-outline kt-badge-primary">Active</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-full bg-yellow-50 text-yellow-600 flex items-center justify-center">
                                    <i class="ki-filled ki-cloud-download"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-foreground">S3 Storage</div>
                                    <div class="text-xs text-muted-foreground">Global tenant bucket</div>
                                </div>
                            </div>
                            <span class="kt-badge kt-badge-outline kt-badge-warning">Checking</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="rounded-xl border border-border bg-background p-6">
                    <h3 class="text-sm font-semibold uppercase text-foreground">{{ strtoupper(__('locale.labels.browsers')) }}</h3>
                    <div class="mt-4" id="browser-session" style="width: 100%; height: 300px;"></div>
                </div>
                <div class="rounded-xl border border-border bg-background p-6">
                    <h3 class="text-sm font-semibold uppercase text-foreground">{{ strtoupper(__('locale.labels.locations')) }}</h3>
                    <div class="mt-4" id="location-session" style="width: 100%; height: 300px;"></div>
                </div>
                <div class="rounded-xl border border-border bg-background p-6">
                    <h3 class="text-sm font-semibold uppercase text-foreground">{{ strtoupper(__('locale.labels.platform_or_os')) }}</h3>
                    <div class="mt-4" id="platform-session" style="width: 100%; height: 300px;"></div>
                </div>
                <div class="rounded-xl border border-border bg-background p-6">
                    <h3 class="text-sm font-semibold uppercase text-foreground">{{ strtoupper(__('locale.labels.devices')) }}</h3>
                    <div class="mt-4" id="client-device-session" style="width: 100%; height: 300px;"></div>
                </div>
            </div>
        @endhasrole
    </section>
@endsection

@push('vendor-scripts')
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.3.3/dist/echarts.min.js"></script>
@endpush

@push('custom-scripts')
    @include('admin.audit-trail.partials.login-history-chartsjs')
@endpush
