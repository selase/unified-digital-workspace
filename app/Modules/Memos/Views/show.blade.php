@extends('layouts.metronic.app')

@section('title', 'Memorandum - ' . $memo->subject)

@section('content')
    <x-metronic.page-header
        title="Institutional Memorandum"
        subtitle="Auditable electronic official correspondence with verified minute trail."
        :breadcrumbs="[
            ['label' => 'Memos', 'url' => route('memos.index')],
            ['label' => 'Memo Registry', 'url' => route('memos.list')],
            ['label' => 'REF-' . strtoupper(substr($memo->uuid, 0, 8))],
        ]"
    >
        <x-slot:actions>
            <a href="{{ route('memos.list') }}" class="kt-btn kt-btn-sm kt-btn-outline">
                <i class="ki-filled ki-arrow-left"></i>
                All Memos
            </a>
            <button onclick="window.print()" class="kt-btn kt-btn-sm kt-btn-outline">
                <i class="ki-filled ki-printer"></i>
                Print / Export
            </button>
            @can('memos.create')
                <a href="{{ route('memos.create') }}" class="kt-btn kt-btn-sm kt-btn-primary">
                    <i class="ki-filled ki-plus"></i>
                    New Memo
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

    <div class="grid gap-6 lg:grid-cols-12">
        <!-- Official Institutional Memo Document Paper -->
        <div class="lg:col-span-8 flex flex-col gap-6">
            <x-metronic.card class="bg-card shadow-sm border border-border">
                <!-- Institutional Letterhead Header -->
                <div class="border-b border-border pb-6 mb-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex flex-col">
                            <span class="text-2xs font-bold tracking-widest text-primary uppercase">Official Correspondence</span>
                            <h2 class="text-xl font-bold tracking-tight text-foreground uppercase mt-1">
                                {{ app(\App\Services\Tenancy\TenantContext::class)->getTenant()?->name ?? config('app.name') }}
                            </h2>
                            <span class="text-xs text-muted-foreground">e-Office Electronic Document Management & Circulation System</span>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            @php
                                $statusVariant = match($memo->status) {
                                    'draft' => 'secondary',
                                    'pending_verification' => 'warning',
                                    'sent' => 'primary',
                                    'acknowledged' => 'success',
                                    'closed' => 'secondary',
                                    default => 'secondary',
                                };
                            @endphp
                            <x-metronic.badge :variant="$statusVariant" :outline="true" size="md">
                                {{ strtoupper(str_replace('_', ' ', $memo->status)) }}
                            </x-metronic.badge>
                            <span class="text-2xs font-mono text-muted-foreground mt-1">REF: MEM-{{ strtoupper(substr($memo->uuid, 0, 8)) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Memo Metadata Table -->
                <div class="bg-muted/20 border border-border rounded-lg p-5 mb-6 text-sm">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 pb-3 border-b border-border/60">
                        <div class="md:col-span-2 font-semibold text-muted-foreground text-xs uppercase tracking-wider">RECIPIENTS:</div>
                        <div class="md:col-span-10 flex flex-wrap gap-2">
                            @forelse($memo->visibleRecipientsFor(auth()->user()) as $recipient)
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-background border border-border text-xs">
                                    <span class="font-medium text-foreground">
                                        {{ $recipient->user?->displayName() ?: ($recipient->recipientSummary()['name'] ?? 'Recipient #' . $recipient->recipient_id) }}
                                    </span>
                                    <span class="text-2xs uppercase text-muted-foreground">({{ $recipient->role }})</span>
                                    @if($recipient->acknowledged_at)
                                        <span class="size-2 rounded-full bg-green-500" title="Acknowledged at {{ $recipient->acknowledged_at->format('d/m/Y H:i') }}"></span>
                                    @elseif($recipient->requires_ack)
                                        <span class="size-2 rounded-full bg-amber-500" title="Pending Acknowledgement"></span>
                                    @endif
                                </div>
                            @empty
                                <span class="text-muted-foreground italic text-xs">No recipients specified</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 py-3 border-b border-border/60">
                        <div class="md:col-span-2 font-semibold text-muted-foreground text-xs uppercase tracking-wider">FROM:</div>
                        <div class="md:col-span-10 font-medium text-foreground">
                            {{ $memo->sender?->displayName() ?: $memo->sender?->email ?: 'Authorized Sender' }}
                            <span class="text-xs text-muted-foreground font-normal">({{ $memo->sender?->email }})</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 py-3 border-b border-border/60">
                        <div class="md:col-span-2 font-semibold text-muted-foreground text-xs uppercase tracking-wider">DATE:</div>
                        <div class="md:col-span-10 text-foreground">
                            {{ $memo->created_at?->format('d/m/Y H:i T') }}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 pt-3">
                        <div class="md:col-span-2 font-semibold text-muted-foreground text-xs uppercase tracking-wider">SUBJECT:</div>
                        <div class="md:col-span-10 font-bold text-foreground uppercase tracking-tight">
                            {{ $memo->subject }}
                        </div>
                    </div>
                </div>

                <!-- Memo Body -->
                <div class="py-4 text-foreground text-base leading-relaxed whitespace-pre-line font-normal">
                    {{ $memo->body }}
                </div>

                <!-- Official Signature Block -->
                <div class="mt-8 pt-6 border-t border-border">
                    @if($memo->signed_at)
                        <div class="inline-flex flex-col p-4 rounded-xl border-2 border-green-600/30 bg-green-50/50 dark:bg-green-950/20 text-xs">
                            <div class="flex items-center gap-2 text-green-700 dark:text-green-400 font-bold uppercase tracking-wider mb-1">
                                <i class="ki-filled ki-shield-tick text-base"></i>
                                Digitally Signed & Approved
                            </div>
                            <p class="font-medium text-foreground">
                                {{ $memo->sender?->displayName() ?: 'Authorized Officer' }}
                            </p>
                            <p class="text-muted-foreground text-2xs">
                                Signed At: {{ $memo->signed_at->format('d/m/Y H:i:s T') }}
                            </p>
                            <p class="font-mono text-2xs text-muted-foreground/80 mt-1">
                                SHA-256: {{ hash('sha256', $memo->uuid . $memo->signed_at) }}
                            </p>
                        </div>
                    @else
                        <div class="p-4 rounded-xl border border-dashed border-amber-300 bg-amber-50/50 dark:bg-amber-950/20 flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center gap-2 text-amber-800 dark:text-amber-400">
                                <i class="ki-filled ki-information-2 text-lg"></i>
                                <div>
                                    <p class="text-xs font-semibold">Unsigned Draft</p>
                                    <p class="text-2xs text-muted-foreground">This memo must be digitally signed before official institutional dispatch.</p>
                                </div>
                            </div>

                            @if($isSender)
                                @can('memos.sign')
                                    <form method="POST" action="{{ route('memos.sign', $memo) }}">
                                        @csrf
                                        <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary flex items-center gap-1.5">
                                            <i class="ki-filled ki-fingerprint"></i>
                                            Sign & Dispatch Now
                                        </button>
                                    </form>
                                @endcan
                            @endif
                        </div>
                    @endif
                </div>
            </x-metronic.card>
        </div>

        <!-- Right Column: Acknowledgement & Minute Sheet -->
        <div class="lg:col-span-4 flex flex-col gap-6">
            <!-- Acknowledgement Action Card -->
            @if($userRecipient && !$userRecipient->acknowledged_at)
                @can('memos.acknowledge')
                    <x-metronic.card title="Acknowledge Receipt" description="Your acknowledgement is required for this memo." class="border-amber-200 dark:border-amber-900/40 bg-amber-50/30 dark:bg-amber-950/10">
                        <p class="text-xs text-muted-foreground mb-4">
                            By clicking below, you confirm that you have read and understood the contents of this memorandum.
                        </p>
                        <form method="POST" action="{{ route('memos.acknowledge', $memo) }}">
                            @csrf
                            <button type="submit" class="w-full kt-btn kt-btn-primary flex items-center justify-center gap-2">
                                <i class="ki-filled ki-check-circle"></i>
                                Acknowledge Receipt
                            </button>
                        </form>
                    </x-metronic.card>
                @endcan
            @endif

            <!-- Institutional Minute Sheet -->
            <x-metronic.card title="Official Minute Sheet" description="Sequential chain of endorsements and directives.">
                <!-- Timeline of Existing Minutes -->
                <div class="space-y-4 mb-6">
                    @forelse($memo->minutes as $index => $minute)
                        <div class="p-3.5 rounded-lg border border-border bg-muted/20 flex flex-col gap-1.5 text-xs">
                            <div class="flex items-center justify-between text-muted-foreground">
                                <span class="font-bold text-foreground">Minute #{{ $index + 1 }}</span>
                                <span class="text-2xs">{{ $minute->created_at?->diffForHumans() }}</span>
                            </div>
                            <p class="text-foreground leading-relaxed">
                                {{ $minute->body }}
                            </p>
                            <div class="mt-1 pt-1 border-t border-border/40 text-2xs text-muted-foreground flex items-center gap-1">
                                <i class="ki-filled ki-user"></i>
                                <span>{{ $minute->author?->displayName() ?: 'Author' }}</span>
                                <span>&bull;</span>
                                <span>{{ $minute->created_at?->format('d/m/Y H:i') }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-muted-foreground">
                            <i class="ki-filled ki-document text-xl mb-1 block opacity-40"></i>
                            No minutes have been appended to this sheet yet.
                        </div>
                    @endforelse
                </div>

                <!-- Add Minute Form -->
                @can('memos.minute')
                    <div class="pt-4 border-t border-border">
                        <form method="POST" action="{{ route('memos.minute', $memo) }}" class="space-y-3">
                            @csrf
                            <div>
                                <label for="minute_body" class="block text-xs font-semibold text-foreground mb-1 uppercase tracking-wider">
                                    Append New Minute
                                </label>
                                <textarea
                                    id="minute_body"
                                    name="body"
                                    rows="3"
                                    required
                                    placeholder="Enter your minute, endorsement, or directive..."
                                    class="w-full px-3 py-2 rounded-lg border border-border bg-background text-foreground text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                ></textarea>
                            </div>
                            <button type="submit" class="w-full kt-btn kt-btn-sm kt-btn-outline flex items-center justify-center gap-1.5">
                                <i class="ki-filled ki-plus"></i>
                                Append Minute to Sheet
                            </button>
                        </form>
                    </div>
                @endcan
            </x-metronic.card>
        </div>
    </div>
@endsection

