@extends('layouts.metronic.app')

@section('title', $message->subject . ' - Direct Message')

@section('content')
<div class="flex flex-col gap-6 max-w-4xl mx-auto">
    <x-metronic.page-header
        title="{{ $message->subject }}"
        description="Private message dispatched on {{ $message->created_at?->format('d/m/Y H:i') }}"
    >
        <x-slot:actions>
            <a href="{{ route('forums.messages.index') }}" class="kt-btn kt-btn-outline">
                Back to Messages
            </a>
            @can('forums.messages.send')
                <a href="{{ route('forums.messages.create') }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New Message
                </a>
            @endcan
        </x-slot:actions>
    </x-metronic.page-header>

    <x-metronic.card title="Message Conversation" subtitle="Encrypted peer transmission">
        <div class="space-y-4">
            <div class="p-4 rounded-xl border border-border bg-muted/20 flex flex-wrap items-center justify-between gap-4 text-xs">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center text-xs shrink-0 border border-primary/20">
                        {{ strtoupper(substr($message->sender?->first_name ?: 'S', 0, 1)) }}
                    </div>
                    <div>
                        <div class="font-semibold text-foreground text-sm">
                            From: {{ $message->sender ? $message->sender->first_name . ' ' . $message->sender->last_name : 'Staff Member' }}
                        </div>
                        <span class="text-muted-foreground">{{ $message->sender?->email }}</span>
                    </div>
                </div>

                <div class="text-right">
                    <span class="text-muted-foreground block">Recipients:</span>
                    <div class="flex flex-wrap gap-1 justify-end mt-0.5">
                        @foreach($message->visibleRecipientsFor(auth()->user()) as $rec)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-muted text-foreground text-2xs font-medium border border-border">
                                <span>{{ $rec->user ? $rec->user->first_name . ' ' . $rec->user->last_name : 'Colleague' }}</span>
                                <span class="uppercase text-muted-foreground">({{ $rec->role ?? 'to' }})</span>
                                @if($rec->read_at)
                                    <span class="ml-0.5 text-emerald-500 font-bold" title="Read {{ $rec->read_at->diffForHumans() }}">&check;&check;</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="p-6 rounded-xl border border-border bg-card text-sm text-foreground leading-relaxed whitespace-pre-wrap">
                {{ $message->body }}
            </div>
        </div>
    </x-metronic.card>
</div>
@endsection

