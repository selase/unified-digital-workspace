@extends('layouts.metronic.app')

@section('title', __('locale.menu.subscription'))

@section('content')
    @php
        $subscriptionRows = $subscriptions ?? collect();
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Tenants</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Tenant Subscriptions</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Review subscription status and renewal timelines.</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-lg font-semibold text-foreground">Subscriptions</h2>
                <span class="text-xs text-muted-foreground">All active and historical plans.</span>
            </div>
            <div class="overflow-x-auto">
                <table class="kt-table" id="tenant-subscriptions-table">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Tenant</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Current Period End</th>
                            <th>Created</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @forelse ($subscriptionRows as $subscription)
                            @php
                                $tenant = $subscription->tenant;
                                $tenantLogo = $tenant?->logo_url ?: $tenant?->gravatar;
                                $status = $subscription->provider_status ?? $subscription->status ?? 'unknown';
                                $statusClass = match ($status) {
                                    'active', 'paid', 'succeeded' => 'kt-badge-success',
                                    'past_due', 'pending' => 'kt-badge-warning',
                                    'cancelled', 'canceled' => 'kt-badge-secondary',
                                    'failed' => 'kt-badge-destructive',
                                    default => 'kt-badge-secondary',
                                };
                                $periodEnd = $subscription->current_period_end ?? $subscription->ends_at;
                            @endphp
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        @if($tenantLogo)
                                            <img src="{{ $tenantLogo }}" alt="{{ $tenant?->name ?? 'Tenant' }}" class="size-10 rounded-full object-cover" />
                                        @else
                                            <div class="size-10 rounded-full bg-muted flex items-center justify-center text-xs text-muted-foreground">NA</div>
                                        @endif
                                        <div class="flex flex-col">
                                            @if($tenant)
                                                <a href="{{ route('tenants.show', $tenant->uuid) }}" class="font-medium text-foreground hover:text-primary">
                                                    {{ $tenant->name }}
                                                </a>
                                                <span class="text-xs text-muted-foreground">{{ $tenant->email }}</span>
                                            @else
                                                <span class="text-sm text-muted-foreground">Unknown Tenant</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $subscription->provider_plan ?? $subscription->name ?? 'N/A' }}</td>
                                <td>
                                    <span class="kt-badge {{ $statusClass }}">{{ strtoupper($status) }}</span>
                                </td>
                                <td>
                                    <span class="text-xs text-muted-foreground">
                                        {{ $periodEnd?->format('M d, Y') ?? 'Auto-renew' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-xs text-muted-foreground">{{ $subscription->created_at?->format('M d, Y') }}</span>
                                </td>
                                <td class="text-right">
                                    @if(Route::has('tenants.subscriptions.show'))
                                        <a href="{{ route('tenants.subscriptions.show', $subscription->uuid ?? $subscription->id) }}" class="kt-btn kt-btn-sm kt-btn-outline">View</a>
                                    @else
                                        <span class="text-xs text-muted-foreground">Unavailable</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-sm text-muted-foreground">No subscriptions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection

@push('styles')
    <link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
@endpush

@push('vendor-scripts')
    <script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
@endpush

@push('custom-scripts')
    <script src="{{ asset('js/scripts.js') }}"></script>
    <script>
        $('#tenant-subscriptions-table').DataTable({
            "language": {
                "lengthMenu": "Show _MENU_",
            },
            "lengthMenu": [[25, 50, 100, 250, 500, 1000, -1], [25, 50, 100, 250, 500, 1000, "All"]],
            "responsive": true,
            "dom":
                "<'flex flex-wrap items-center justify-between gap-4 mb-4'lf>" +
                "<'table-responsive'tr>" +
                "<'flex flex-wrap items-center justify-between gap-4 mt-4'ip>",
        });
    </script>
@endpush
