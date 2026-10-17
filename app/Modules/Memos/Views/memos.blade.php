@extends('layouts.metronic.app')

@section('title', 'Memo Registry')

@section('content')
    <x-metronic.page-header
        title="Memoranda Registry"
        subtitle="Complete institutional index of all correspondence, circulars, and departmental directives."
        :breadcrumbs="[
            ['label' => 'Workspace', 'url' => route('tenant.dashboard')],
            ['label' => 'Memos Hub', 'url' => route('memos.index')],
            ['label' => 'Registry'],
        ]"
    >
        <x-slot:actions>
            <a href="{{ route('memos.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">
                <i class="ki-filled ki-arrow-left"></i>
                Memos Hub
            </a>
            @can('memos.create')
                <a href="{{ route('memos.create') }}" class="kt-btn kt-btn-sm kt-btn-primary">
                    <i class="ki-filled ki-plus"></i>
                    Draft New Memo
                </a>
            @endcan
        </x-slot:actions>
    </x-metronic.page-header>

    @if (session('message'))
        <div class="mb-6 p-4 rounded-xl border border-green-200 bg-green-50 text-green-800 dark:border-green-900/50 dark:bg-green-950/20 dark:text-green-400 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="ki-filled ki-check-circle text-lg"></i>
                <span class="text-sm font-medium">{{ session('message') }}</span>
            </div>
        </div>
    @endif

    <div class="flex flex-col gap-6">
        <!-- Search and Filter Bar Card -->
        <x-metronic.card title="Memo List" description="Search and filter memoranda across the institution">
            <form method="GET" action="{{ route('memos.list') }}" class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3 flex-1 min-w-[300px]">
                    <div class="relative flex-1 min-w-[200px]">
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Search by subject or keyword..."
                            class="w-full pl-9 pr-4 py-2 rounded-lg border border-border bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                        />
                        <i class="ki-filled ki-magnifier absolute left-3 top-2.5 text-muted-foreground text-sm"></i>
                    </div>

                    <select
                        name="status"
                        onchange="this.form.submit()"
                        class="px-3 py-2 rounded-lg border border-border bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                    >
                        <option value="">All Circulation Statuses</option>
                        <option value="draft" @selected($status === 'draft')>Draft</option>
                        <option value="pending_verification" @selected($status === 'pending_verification')>Pending Verification</option>
                        <option value="sent" @selected($status === 'sent')>Dispatched / Sent</option>
                        <option value="acknowledged" @selected($status === 'acknowledged')>Acknowledged</option>
                        <option value="closed" @selected($status === 'closed')>Closed</option>
                    </select>

                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">Filter</button>

                    @if($search !== '' || $status !== '')
                        <a href="{{ route('memos.list') }}" class="kt-btn kt-btn-sm kt-btn-outline">Reset</a>
                    @endif
                </div>

                <div class="text-xs text-muted-foreground">
                    Showing <span class="font-semibold text-foreground">{{ $memos->total() }}</span> memoranda
                </div>
            </form>
        </x-metronic.card>

        <!-- Memoranda Table -->
        <x-metronic.table>
            <x-slot:head>
                <tr>
                    <th class="px-5 py-3.5">Reference & Subject</th>
                    <th class="px-5 py-3.5">Sender</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5">Recipients</th>
                    <th class="px-5 py-3.5">Last Updated</th>
                    <th class="px-5 py-3.5 text-right">Action</th>
                </tr>
            </x-slot:head>

            @forelse($memos as $memo)
                <tr class="hover:bg-muted/30 transition-colors">
                    <td class="px-5 py-3.5">
                        <a href="{{ route('memos.show', $memo) }}" class="font-semibold text-foreground hover:text-primary transition-colors block">
                            {{ $memo->subject }}
                        </a>
                        <span class="text-2xs font-mono text-muted-foreground">REF: MEM-{{ strtoupper(substr($memo->uuid, 0, 8)) }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-foreground">
                        {{ $memo->sender?->displayName() ?: $memo->sender?->email ?: 'Authorized Officer' }}
                    </td>
                    <td class="px-5 py-3.5">
                        @php
                            $badgeVariant = match($memo->status) {
                                'draft' => 'secondary',
                                'pending_verification' => 'warning',
                                'sent' => 'primary',
                                'acknowledged' => 'success',
                                'closed' => 'secondary',
                                default => 'secondary',
                            };
                        @endphp
                        <x-metronic.badge :variant="$badgeVariant" size="sm">
                            {{ strtoupper(str_replace('_', ' ', $memo->status)) }}
                        </x-metronic.badge>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-muted-foreground">
                        {{ $memo->recipients_count }} addressee(s)
                    </td>
                    <td class="px-5 py-3.5 text-xs text-muted-foreground">
                        {{ $memo->updated_at?->diffForHumans() }}
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <a href="{{ route('memos.show', $memo) }}" class="kt-btn kt-btn-xs kt-btn-outline inline-flex items-center gap-1">
                            Open
                            <i class="ki-filled ki-right text-xs"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="py-12 text-center text-muted-foreground text-sm">
                        <i class="ki-filled ki-document text-3xl block mb-2 opacity-40"></i>
                        No memoranda match your search criteria.
                    </td>
                </tr>
            @endforelse

            @if($memos->hasPages())
                <x-slot:pagination>
                    {{ $memos->links() }}
                </x-slot:pagination>
            @endif
        </x-metronic.table>
    </div>
@endsection
