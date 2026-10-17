@extends('layouts.metronic.app')

@section('title', 'Start New Discussion Thread')

@section('content')
<div class="flex flex-col gap-6 max-w-4xl mx-auto">
    <x-metronic.page-header
        title="Start New Discussion Thread"
        description="Initiate an institutional discussion, collaborative enquiry, or cross-departmental dialogue."
    >
        <x-slot:actions>
            <a href="{{ route('forums.threads.index') }}" class="kt-btn kt-btn-outline">
                Cancel
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    @if ($errors->any())
        <div class="rounded-xl border border-destructive/20 bg-destructive/10 p-4 text-destructive text-sm space-y-1">
            <p class="font-semibold">Please correct the following errors:</p>
            <ul class="list-disc list-inside text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-metronic.card title="Discussion Parameters" subtitle="Specify topic categorization and initial message content">
        <form method="POST" action="{{ route('forums.threads.store') }}" class="flex flex-col gap-5">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="channel_id" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Target Channel <span class="text-destructive">*</span>
                    </label>
                    <select id="channel_id" name="channel_id" required class="kt-select w-full">
                        <option value="">Select a channel…</option>
                        @foreach($channels as $c)
                            <option value="{{ $c->id }}" @selected((int) old('channel_id', $selectedChannelId) === $c->id)>
                                #{{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label for="tags" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Tags (Optional)
                    </label>
                    <input
                        type="text"
                        id="tags"
                        name="tags"
                        value="{{ old('tags') }}"
                        placeholder="Comma-separated e.g. quality, clinical, triage"
                        class="kt-input w-full"
                    />
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="title" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Thread Title <span class="text-destructive">*</span>
                </label>
                <input
                    type="text"
                    id="title"
                    name="title"
                    value="{{ old('title') }}"
                    required
                    placeholder="Clear, descriptive topic summary…"
                    class="kt-input w-full font-medium"
                />
            </div>

            <div class="space-y-1.5">
                <label for="body" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Initial Discussion Content <span class="text-destructive">*</span>
                </label>
                <textarea
                    id="body"
                    name="body"
                    rows="6"
                    required
                    placeholder="Describe the clinical issue, administrative workflow, question, or background in detail…"
                    class="kt-input w-full p-3 h-auto"
                >{{ old('body') }}</textarea>
            </div>

            <div class="mt-4 pt-4 border-t border-border flex items-center justify-end gap-3">
                <a href="{{ route('forums.threads.index') }}" class="kt-btn kt-btn-outline">
                    Cancel
                </a>
                <button type="submit" class="kt-btn kt-btn-primary">
                    Publish Discussion
                </button>
            </div>
        </form>
    </x-metronic.card>
</div>
@endsection

