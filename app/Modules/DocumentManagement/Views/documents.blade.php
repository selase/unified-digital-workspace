@extends('layouts.metronic.app')

@section('title', 'Controlled Document Library')

@section('content')
    <div class="grid gap-5">
        <x-metronic.page-header
            title="Controlled Document Library"
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Document Management', 'url' => route('document-management.index')],
                ['label' => 'Library'],
            ]"
        >
            <x-slot:actions>
                <div class="flex flex-wrap items-center gap-2">
                    @if(! ($billingOwnScope ?? false))
                    <a href="{{ route('document-management.compliance.index') }}" class="kt-btn kt-btn-outline flex items-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        Compliance Overview
                    </a>
                    @endif
                    <a href="{{ route('document-management.index') }}" class="kt-btn kt-btn-outline">
                        Back to Hub
                    </a>
                    @if(! ($billingOwnScope ?? false) && auth()->user()->can('documents.create'))
                        <a href="{{ route('document-management.documents.create') }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Register Document
                        </a>
                    @endif
                </div>
            </x-slot:actions>
        </x-metronic.page-header>

        <x-metronic.card title="Document Catalog" subtitle="Official controlled policies, standard operating procedures, and clinical guidelines">
            <x-slot:headerActions>
                <form class="flex flex-wrap items-center gap-2" method="GET" action="{{ route('document-management.documents.index') }}">
                    <input
                        class="kt-input w-full min-w-[200px] sm:w-[220px]"
                        name="search"
                        placeholder="Search title or reference..."
                        type="text"
                        value="{{ $search }}"
                    />
                    <select class="kt-select min-w-[140px]" name="status">
                        <option value="">All ISO Statuses</option>
                        @foreach($statuses as $stVal => $stLbl)
                            <option value="{{ $stVal }}" @selected($status === $stVal)>{{ $stLbl }}</option>
                        @endforeach
                    </select>
                    <select class="kt-select min-w-[160px]" name="category">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" @selected(($category ?? '') === $cat)>{{ $cat }}</option>
                        @endforeach
                    </select>
                    <button class="kt-btn kt-btn-outline" type="submit">Filter</button>
                    @if($search || $status || !empty($category))
                        <a href="{{ route('document-management.documents.index') }}" class="kt-btn kt-btn-ghost text-xs">Reset</a>
                    @endif
                </form>
            </x-slot:headerActions>

            <div class="overflow-x-auto">
                <x-metronic.table>
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Document Title</th>
                            <th>ISO Stage</th>
                            <th>Active Version</th>
                            <th>Category</th>
                            <th>Review Due</th>
                            <th>Last Updated</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs text-foreground divide-y divide-border/60">
                        @forelse($documents as $document)
                            @php
                                $badgeVariant = match ($document->status) {
                                    'published' => 'success',
                                    'approved' => 'primary',
                                    'in_review', 'under_revision' => 'warning',
                                    'obsolete' => 'destructive',
                                    default => 'outline',
                                };
                            @endphp
                            <tr class="hover:bg-muted/30 transition-colors">
                                <td>
                                    <div class="flex flex-col">
                                        <a
                                            href="{{ route('document-management.documents.show', $document) }}"
                                            class="font-semibold text-foreground hover:text-primary transition-colors text-sm"
                                        >
                                            {{ $document->title }}
                                        </a>
                                        <span class="text-[11px] text-muted-foreground font-mono">{{ $document->slug }}</span>
                                    </div>
                                </td>
                                <td>
                                    <x-metronic.badge :variant="$badgeVariant" size="sm">
                                        {{ \App\Modules\DocumentManagement\Models\Document::STATUS_LABELS[$document->status] ?? ucfirst($document->status) }}
                                    </x-metronic.badge>
                                </td>
                                <td>
                                    @if($document->currentVersion)
                                        <x-metronic.badge variant="primary" size="sm">
                                            v{{ $document->currentVersion->version_number }}.0
                                        </x-metronic.badge>
                                    @else
                                        <span class="text-muted-foreground italic">None</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="px-2 py-0.5 rounded bg-muted/40 font-medium text-foreground">
                                        {{ $document->category ?: 'Unassigned' }}
                                    </span>
                                </td>
                                <td>
                                    @if($document->review_due_at)
                                        <span class="{{ $document->isReviewOverdue() ? 'text-destructive font-bold' : 'text-muted-foreground' }}">
                                            {{ $document->review_due_at->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span class="text-muted-foreground">—</span>
                                    @endif
                                </td>
                                <td class="text-muted-foreground whitespace-nowrap">
                                    {{ $document->updated_at?->diffForHumans() }}
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a
                                            href="{{ route('document-management.documents.show', $document) }}"
                                            class="kt-btn kt-btn-outline py-1 px-2.5 text-xs"
                                        >
                                            View
                                        </a>
                                        @if($document->currentVersion)
                                            <a
                                                href="{{ route('document-management.documents.download', ['document' => $document]) }}"
                                                class="kt-btn kt-btn-ghost py-1 px-2 text-xs"
                                                title="Download active master file"
                                            >
                                                <svg class="h-3.5 w-3.5 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-muted-foreground">
                                    <p class="font-medium text-sm text-foreground">No controlled documents found.</p>
                                    <p class="text-xs text-muted-foreground mt-1">Try adjusting your filters or register a new document.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-metronic.table>
            </div>

            @if($documents->hasPages())
                <div class="mt-4 pt-4 border-t border-border">
                    {{ $documents->links() }}
                </div>
            @endif
        </x-metronic.card>
    </div>
@endsection
