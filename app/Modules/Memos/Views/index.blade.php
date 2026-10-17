@extends('layouts.metronic.app')

@section('title', 'Memos Hub')

@section('content')
    @php
        $draftCount = (int) ($statusCounts[\App\Modules\Memos\Models\Memo::STATUS_DRAFT] ?? 0);
        $pendingVerificationCount = (int) ($statusCounts[\App\Modules\Memos\Models\Memo::STATUS_PENDING] ?? 0);
        $sentCount = (int) ($statusCounts[\App\Modules\Memos\Models\Memo::STATUS_SENT] ?? 0);
        $acknowledgedCount = (int) ($statusCounts[\App\Modules\Memos\Models\Memo::STATUS_ACKNOWLEDGED] ?? 0);
    @endphp

    <x-metronic.page-header
        title="Memos Hub"
        subtitle="Institutional e-Office memorandum circulation, minute tracking, and digital endorsements."
        :breadcrumbs="[
            ['label' => 'Workspace', 'url' => route('tenant.dashboard')],
            ['label' => 'Memos Hub'],
        ]"
    >
        <x-slot:actions>
            <a href="{{ route('memos.list') }}" class="kt-btn kt-btn-sm kt-btn-outline">
                <i class="ki-filled ki-burger-menu-2"></i>
                All Memos
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

    <section class="grid gap-6">
        <!-- Metric KPI Cards -->
        <div class="grid gap-4 md:grid-cols-4">
            <x-metronic.card class="bg-card">
                <div class="flex items-center justify-between">
                    <p class="text-xs uppercase font-medium tracking-wide text-muted-foreground">Drafts</p>
                    <span class="size-8 rounded-lg bg-muted/40 flex items-center justify-center text-muted-foreground">
                        <i class="ki-filled ki-file-down"></i>
                    </span>
                </div>
                <p class="mt-2 text-2xl font-bold tracking-tight text-foreground">{{ $draftCount }}</p>
                <p class="text-2xs text-muted-foreground mt-1">Pending dispatch or signature</p>
            </x-metronic.card>

            <x-metronic.card class="bg-card">
                <div class="flex items-center justify-between">
                    <p class="text-xs uppercase font-medium tracking-wide text-muted-foreground">Pending</p>
                    <span class="size-8 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-600 dark:text-amber-400">
                        <i class="ki-filled ki-time"></i>
                    </span>
                </div>
                <p class="mt-2 text-2xl font-bold tracking-tight text-foreground">{{ $pendingVerificationCount }}</p>
                <p class="text-2xs text-muted-foreground mt-1">Awaiting 2FA code / check</p>
            </x-metronic.card>

            <x-metronic.card class="bg-card">
                <div class="flex items-center justify-between">
                    <p class="text-xs uppercase font-medium tracking-wide text-muted-foreground">Dispatched</p>
                    <span class="size-8 rounded-lg bg-blue-500/10 flex items-center justify-center text-primary">
                        <i class="ki-filled ki-send"></i>
                    </span>
                </div>
                <p class="mt-2 text-2xl font-bold tracking-tight text-foreground">{{ $sentCount }}</p>
                <p class="text-2xs text-muted-foreground mt-1">In active circulation</p>
            </x-metronic.card>

            <x-metronic.card class="bg-card">
                <div class="flex items-center justify-between">
                    <p class="text-xs uppercase font-medium tracking-wide text-muted-foreground">Acknowledged</p>
                    <span class="size-8 rounded-lg bg-green-500/10 flex items-center justify-center text-green-600 dark:text-green-400">
                        <i class="ki-filled ki-shield-tick"></i>
                    </span>
                </div>
                <p class="mt-2 text-2xl font-bold tracking-tight text-foreground">{{ $acknowledgedCount }}</p>
                <p class="text-2xs text-muted-foreground mt-1">Fully read & acknowledged</p>
            </x-metronic.card>
        </div>

        <!-- Main Content Grid -->
        <div class="grid gap-6 xl:grid-cols-12">
            <!-- Recent Memos Table -->
            <div class="xl:col-span-8 flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-foreground">Recent Memoranda</h3>
                    <a href="{{ route('memos.list') }}" class="text-xs font-medium text-primary hover:underline">View all memos &rarr;</a>
                </div>

                <x-metronic.table>
                    <x-slot:head>
                        <tr>
                            <th class="px-5 py-3.5">Subject & Reference</th>
                            <th class="px-5 py-3.5">Sender</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5">Recipients</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </x-slot:head>

                    @forelse($recentMemos as $memo)
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-5 py-3.5">
                                <a href="{{ route('memos.show', $memo) }}" class="font-semibold text-foreground hover:text-primary transition-colors block">
                                    {{ $memo->subject }}
                                </a>
                                <span class="text-2xs font-mono text-muted-foreground">MEM-{{ strtoupper(substr($memo->uuid, 0, 8)) }} &bull; {{ $memo->updated_at?->diffForHumans() }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-sm text-foreground">
                                {{ $memo->sender?->displayName() ?: $memo->sender?->email ?: 'System' }}
                            </td>
                            <td class="px-5 py-3.5">
                                @php
                                    $variant = match($memo->status) {
                                        'draft' => 'secondary',
                                        'pending_verification' => 'warning',
                                        'sent' => 'primary',
                                        'acknowledged' => 'success',
                                        default => 'secondary',
                                    };
                                @endphp
                                <x-metronic.badge :variant="$variant" size="sm">
                                    {{ strtoupper(str_replace('_', ' ', $memo->status)) }}
                                </x-metronic.badge>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-muted-foreground">
                                {{ $memo->recipients->count() }} addressee(s)
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('memos.show', $memo) }}" class="kt-btn kt-btn-xs kt-btn-outline inline-flex items-center gap-1">
                                    View
                                    <i class="ki-filled ki-right text-xs"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-muted-foreground text-sm">
                                <i class="ki-filled ki-document text-2xl block mb-1 opacity-40"></i>
                                No memos created yet. Click "Draft New Memo" to begin.
                            </td>
                        </tr>
                    @endforelse
                </x-metronic.table>
            </div>

            <!-- Execution Snapshot -->
            <div class="xl:col-span-4 flex flex-col gap-4">
                <h3 class="text-base font-semibold text-foreground">Circulation Snapshot</h3>

                <x-metronic.card>
                    <div class="space-y-4">
                        <div class="p-4 rounded-xl bg-muted/20 border border-border flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Acks Required</p>
                                <p class="text-xl font-bold text-foreground mt-1">{{ $acknowledgementRequiredCount }}</p>
                            </div>
                            <span class="size-10 rounded-lg bg-background border border-border flex items-center justify-center text-foreground font-bold">
                                <i class="ki-filled ki-shield-tick"></i>
                            </span>
                        </div>

                        <div class="p-4 rounded-xl bg-muted/20 border border-border flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Pending Acks</p>
                                <p class="text-xl font-bold {{ $pendingAcknowledgementCount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground' }} mt-1">
                                    {{ $pendingAcknowledgementCount }}
                                </p>
                            </div>
                            <span class="size-10 rounded-lg bg-background border border-border flex items-center justify-center {{ $pendingAcknowledgementCount > 0 ? 'text-amber-600' : 'text-foreground' }}">
                                <i class="ki-filled ki-time"></i>
                            </span>
                        </div>

                        <div class="p-4 rounded-xl bg-muted/20 border border-border flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Open Actions</p>
                                <p class="text-xl font-bold text-foreground mt-1">{{ $openActionCount }}</p>
                            </div>
                            <span class="size-10 rounded-lg bg-background border border-border flex items-center justify-center text-foreground">
                                <i class="ki-filled ki-task"></i>
                            </span>
                        </div>
                    </div>
                </x-metronic.card>
            </div>
        </div>
    </section>
@endsection
