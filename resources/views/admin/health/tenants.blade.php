@extends('layouts.metronic.app')

@section('title', __('Tenant Health'))

@section('content')
    @php
        $tenantCollection = collect($tenants);
        $totalTenants = $tenantCollection->count();
        $customDomainCount = $tenantCollection->filter(fn ($tenant) => !empty($tenant['custom_domain']))->count();
        $domainIssueCount = $tenantCollection->filter(function ($tenant) {
            if (empty($tenant['custom_domain'])) {
                return false;
            }

            return ($tenant['domain_status'] ?? null) !== 'active' || !($tenant['is_resolvable'] ?? false);
        })->count();
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Operations</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Tenant Health</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Track domain and database health for each tenant.</p>
                </div>
                <div class="flex items-center gap-2 rounded-lg border border-border bg-muted/30 p-2">
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-primary" data-health-filter="all">All</button>
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" data-health-filter="issues">Issues Only</button>
                </div>
                <div class="w-full sm:w-72">
                    <input type="text" data-kt-user-table-filter="search" class="kt-input w-full" placeholder="Search tenants" />
                </div>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Total Tenants</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $totalTenants }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">With Custom Domains</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $customDomainCount }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Domain Issues</p>
                    <p class="mt-2 text-xl font-semibold {{ $domainIssueCount > 0 ? 'text-destructive' : 'text-foreground' }}">{{ $domainIssueCount }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border" id="kt_table_tenant_health">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Tenant</th>
                            <th>Package</th>
                            <th>Custom Domain</th>
                            <th>Domain Status</th>
                            <th>DB Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @foreach ($tenants as $tenant)
                            @php
                                $hasDomainIssue = !empty($tenant['custom_domain']) && (($tenant['domain_status'] ?? null) !== 'active' || !($tenant['is_resolvable'] ?? false));
                            @endphp
                            <tr data-health-meta="{{ strtolower($tenant['name'] . ' ' . $tenant['slug'] . ' ' . $tenant['package']) }}" data-domain-issue="{{ $hasDomainIssue ? '1' : '0' }}">
                                <td>
                                    <div class="flex flex-col">
                                        <span class="font-medium text-foreground">{{ $tenant['name'] }}</span>
                                        <span class="text-xs text-muted-foreground">{{ $tenant['slug'] }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="kt-badge kt-badge-outline kt-badge-primary">{{ $tenant['package'] }}</span>
                                </td>
                                <td>
                                    @if($tenant['custom_domain'])
                                        <a href="https://{{ $tenant['custom_domain'] }}" target="_blank" class="text-primary hover:underline">{{ $tenant['custom_domain'] }}</a>
                                    @else
                                        <span class="text-muted-foreground">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    @if($tenant['custom_domain'])
                                        @if($tenant['domain_status'] === 'active')
                                            @if($tenant['is_resolvable'])
                                                <span class="kt-badge kt-badge-success kt-badge-outline">Active & Resolving</span>
                                            @else
                                                <span class="kt-badge kt-badge-danger kt-badge-outline">Unreachable</span>
                                            @endif
                                        @else
                                            <span class="kt-badge kt-badge-warning kt-badge-outline">{{ ucfirst($tenant['domain_status']) }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted-foreground">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="kt-badge kt-badge-outline {{ str_contains(strtolower((string) $tenant['db_status']), 'ok') ? 'kt-badge-success' : 'kt-badge-warning' }}">{{ $tenant['db_status'] }}</span>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('tenants.change', $tenant['id']) }}" class="kt-btn kt-btn-sm kt-btn-primary">
                                        Switch
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection

@push('custom-scripts')
    <script>
        const tenantRows = document.querySelectorAll('#kt_table_tenant_health tbody tr');
        let activeFilter = 'all';
        let activeQuery = '';

        const applyTenantHealthFilter = () => {
            tenantRows.forEach((row) => {
                const searchMeta = (row.getAttribute('data-health-meta') || '').toLowerCase();
                const hasIssue = (row.getAttribute('data-domain-issue') || '0') === '1';

                const matchesQuery = activeQuery.length === 0 || searchMeta.includes(activeQuery);
                const matchesScope = activeFilter === 'all' || hasIssue;

                row.style.display = matchesQuery && matchesScope ? '' : 'none';
            });
        };

        document.querySelector('[data-kt-user-table-filter="search"]')?.addEventListener('keyup', function (event) {
            activeQuery = event.target.value.toLowerCase().trim();
            applyTenantHealthFilter();
        });

        document.querySelectorAll('[data-health-filter]').forEach((button) => {
            button.addEventListener('click', () => {
                activeFilter = button.getAttribute('data-health-filter') || 'all';

                document.querySelectorAll('[data-health-filter]').forEach((item) => {
                    item.classList.remove('kt-btn-primary');
                    item.classList.add('kt-btn-outline');
                });

                button.classList.add('kt-btn-primary');
                button.classList.remove('kt-btn-outline');

                applyTenantHealthFilter();
            });
        });
    </script>
@endpush
