@extends('layouts.metronic.app')

@section('title', 'Create Discussion Channel')

@section('content')
<div class="flex flex-col gap-6 max-w-3xl mx-auto">
    <x-metronic.page-header
        title="Establish Discussion Channel"
        description="Create a dedicated topical or departmental forum space for institutional collaboration."
    >
        <x-slot:actions>
            <a href="{{ route('forums.channels.index') }}" class="kt-btn kt-btn-outline">
                Back to Channels
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    @if ($errors->any())
        <div class="rounded-xl border border-destructive/20 bg-destructive/10 p-4 text-destructive text-sm space-y-1">
            <p class="font-semibold">Please resolve the input issues below:</p>
            <ul class="list-disc list-inside text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-metronic.card title="Channel Specifications" subtitle="Define channel scope and default access rules">
        <form method="POST" action="{{ route('forums.channels.store') }}" class="flex flex-col gap-5">
            @csrf

            <div class="space-y-1.5">
                <label for="name" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Channel Name <span class="text-destructive">*</span>
                </label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    placeholder="e.g. Clinical Governance & Audit"
                    class="kt-input w-full"
                />
                <p class="text-xs text-muted-foreground">Will generate a clean system slug automatically.</p>
            </div>

            <div class="space-y-1.5">
                <label for="description" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Description & Objectives
                </label>
                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    placeholder="State the intended scope, target participants, and discussion themes for this channel…"
                    class="kt-input w-full p-3 h-auto"
                >{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="sort_order" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Display Priority / Sort Order
                    </label>
                    <input
                        type="number"
                        id="sort_order"
                        name="sort_order"
                        value="{{ old('sort_order', 0) }}"
                        min="0"
                        class="kt-input w-full"
                    />
                    <p class="text-xs text-muted-foreground">Channels with lower numbers appear higher in lists.</p>
                </div>

                <div class="space-y-1.5 flex flex-col justify-end">
                    <label class="flex items-center gap-2 text-sm text-foreground cursor-pointer p-3 rounded-lg border border-border bg-muted/20">
                        <input
                            type="checkbox"
                            name="is_locked"
                            value="1"
                            @checked(old('is_locked'))
                            class="kt-checkbox"
                        />
                        <div>
                            <span class="font-medium text-xs block">Lock on Creation</span>
                            <span class="text-2xs text-muted-foreground">Only moderators will be able to start new threads.</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-border flex items-center justify-end gap-3">
                <a href="{{ route('forums.channels.index') }}" class="kt-btn kt-btn-outline">
                    Cancel
                </a>
                <button type="submit" class="kt-btn kt-btn-primary">
                    Establish Channel
                </button>
            </div>
        </form>
    </x-metronic.card>
</div>
@endsection

