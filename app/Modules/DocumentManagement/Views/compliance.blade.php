@extends('layouts.metronic.app')

@section('title', "Read Compliance: {$document->title}")

@section('content')
    <div class="grid gap-5">
        <x-metronic.page-header
            :title="'Read Compliance: ' . $document->title"
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Document Management', 'url' => route('document-management.index')],
                ['label' => 'Controlled Library', 'url' => route('document-management.documents.index')],
                ['label' => $document->slug, 'url' => route('document-management.documents.show', $document)],
                ['label' => 'Compliance Report'],
            ]"
        >
            <x-slot:actions>
                <a href="{{ route('document-management.documents.show', $document) }}" class="kt-btn kt-btn-outline">
                    Back to Document
                </a>
            </x-slot:actions>
        </x-metronic.page-header>

        {{-- Metrics Overview Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="kt-card p-4 border border-border">
                <span class="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Compliance Rate</span>
                <div class="text-2xl font-bold text-foreground mt-1">{{ $complianceRate }}%</div>
                <div class="w-full bg-muted/40 rounded-full h-1.5 mt-2">
                    <div class="bg-primary h-1.5 rounded-full" style="width: {{ $complianceRate }}%"></div>
                </div>
            </div>

            <div class="kt-card p-4 border border-border">
                <span class="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Acknowledged Staff</span>
                <div class="text-2xl font-bold text-success mt-1">{{ $acknowledgedCount }}</div>
                <span class="text-2xs text-muted-foreground">Confirmed read and understood</span>
            </div>

            <div class="kt-card p-4 border border-border">
                <span class="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Pending Staff</span>
                <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $totalUsers - $acknowledgedCount }}</div>
                <span class="text-2xs text-muted-foreground">Out of {{ $totalUsers }} total active personnel</span>
            </div>
        </div>

        {{-- Staff Roster & Acknowledgement Status Table --}}
        <x-metronic.card title="Personnel Acknowledgement Roster" subtitle="Complete record of verified employee sign-offs for this standard">
            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border w-full text-xs">
                    <thead>
                        <tr class="border-b border-border text-muted-foreground font-semibold">
                            <th class="py-2.5 text-start">Staff Member</th>
                            <th class="py-2.5 text-start">Email Address</th>
                            <th class="py-2.5 text-start">Status</th>
                            <th class="py-2.5 text-start">Acknowledged At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($tenantUsers as $u)
                            @php
                                $ack = $acknowledgements->get($u->uuid);
                            @endphp
                            <tr>
                                <td class="py-2.5 font-semibold text-foreground">{{ $u->name }}</td>
                                <td class="py-2.5 text-muted-foreground">{{ $u->email }}</td>
                                <td class="py-2.5">
                                    @if($ack)
                                        <span class="kt-badge kt-badge-xs kt-badge-success">Acknowledged</span>
                                    @else
                                        <span class="kt-badge kt-badge-xs kt-badge-warning">Pending</span>
                                    @endif
                                </td>
                                <td class="py-2.5 text-muted-foreground">
                                    {{ $ack ? $ack->acknowledged_at->format('d/m/Y H:i') : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted-foreground">No personnel records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-metronic.card>
    </div>
@endsection

