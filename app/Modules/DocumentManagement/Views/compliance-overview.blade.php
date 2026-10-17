@extends('layouts.metronic.app')

@section('title', 'Controlled Documents Compliance Overview')

@section('content')
    <div class="grid gap-5">
        <x-metronic.page-header
            title="ISO Document Compliance Overview"
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Document Management', 'url' => route('document-management.index')],
                ['label' => 'Compliance Overview'],
            ]"
        >
            <x-slot:actions>
                <a href="{{ route('document-management.documents.index') }}" class="kt-btn kt-btn-outline">
                    Back to Library
                </a>
            </x-slot:actions>
        </x-metronic.page-header>

        <x-metronic.card title="Catalog Standards & Read Acknowledgement Compliance" subtitle="Cross-institutional audit report of employee adherence to active clinical and operational standards">
            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border w-full text-xs">
                    <thead>
                        <tr class="border-b border-border text-muted-foreground font-semibold">
                            <th class="py-2.5 text-start">Document Title</th>
                            <th class="py-2.5 text-start">Category</th>
                            <th class="py-2.5 text-start">Effective Date</th>
                            <th class="py-2.5 text-start">Sign-Offs</th>
                            <th class="py-2.5 text-start">Compliance Rate</th>
                            <th class="py-2.5 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($documents as $doc)
                            @php
                                $rate = $totalUsers > 0 ? (int) round(($doc->acknowledgements_count / $totalUsers) * 100) : 0;
                            @endphp
                            <tr>
                                <td class="py-2.5 font-semibold text-foreground">
                                    <a href="{{ route('document-management.documents.show', $doc) }}" class="hover:text-primary">
                                        {{ $doc->title }}
                                    </a>
                                </td>
                                <td class="py-2.5 text-muted-foreground">{{ $doc->category ?? '—' }}</td>
                                <td class="py-2.5 text-muted-foreground">{{ $doc->published_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="py-2.5 font-mono">{{ $doc->acknowledgements_count }} / {{ $totalUsers }}</td>
                                <td class="py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 bg-muted/40 rounded-full h-1.5">
                                            <div class="bg-primary h-1.5 rounded-full" style="width: {{ $rate }}%"></div>
                                        </div>
                                        <span class="font-bold {{ $rate >= 80 ? 'text-success' : 'text-amber-600 dark:text-amber-400' }}">{{ $rate }}%</span>
                                    </div>
                                </td>
                                <td class="py-2.5 text-end">
                                    <a href="{{ route('document-management.documents.compliance', $doc) }}" class="kt-btn kt-btn-2xs kt-btn-outline">
                                        View Roster
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted-foreground">No published controlled documents found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-metronic.card>
    </div>
@endsection

