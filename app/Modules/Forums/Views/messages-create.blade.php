@extends('layouts.metronic.app')

@section('title', 'Compose Direct Message')

@section('content')
<div class="flex flex-col gap-6 max-w-3xl mx-auto">
    <x-metronic.page-header
        title="Compose Direct Message"
        description="Dispatch a private, encrypted message to an authorized colleague within your institution."
    >
        <x-slot:actions>
            <a href="{{ route('forums.messages.index') }}" class="kt-btn kt-btn-outline">
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

    <x-metronic.card title="Message Details" subtitle="Recipient assignment and message content">
        <form method="POST" action="{{ route('forums.messages.store') }}" class="flex flex-col gap-5">
            @csrf

            <div class="space-y-1.5">
                <label for="recipient_id" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Primary Recipient (TO) <span class="text-destructive">*</span>
                </label>
                <select id="recipient_id" name="recipient_id" class="kt-select w-full">
                    <option value="">Select a colleague…</option>
                    @foreach($users as $user)
                        <option value="{{ $user->uuid }}" @selected(old('recipient_id') === $user->uuid)>
                            {{ $user->first_name }} {{ $user->last_name }} ({{ $user->email }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="cc_user_ids" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        CC Recipients (Optional)
                    </label>
                    <select id="cc_user_ids" name="cc_user_ids[]" multiple class="kt-select w-full h-24">
                        @foreach($users as $user)
                            <option value="{{ $user->uuid }}">
                                {{ $user->first_name }} {{ $user->last_name }} ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label for="bcc_user_ids" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        BCC Recipients (Hidden from To/CC)
                    </label>
                    <select id="bcc_user_ids" name="bcc_user_ids[]" multiple class="kt-select w-full h-24">
                        @foreach($users as $user)
                            <option value="{{ $user->uuid }}">
                                {{ $user->first_name }} {{ $user->last_name }} ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="subject" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Subject <span class="text-destructive">*</span>
                </label>
                <input
                    type="text"
                    id="subject"
                    name="subject"
                    value="{{ old('subject') }}"
                    required
                    placeholder="Brief summary of message purpose…"
                    class="kt-input w-full"
                />
            </div>

            <div class="space-y-1.5">
                <label for="body" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Message Content <span class="text-destructive">*</span>
                </label>
                <textarea
                    id="body"
                    name="body"
                    rows="6"
                    required
                    placeholder="Type your confidential communication here…"
                    class="kt-input w-full p-3 h-auto"
                >{{ old('body') }}</textarea>
            </div>

            <div class="mt-4 pt-4 border-t border-border flex items-center justify-end gap-3">
                <a href="{{ route('forums.messages.index') }}" class="kt-btn kt-btn-outline">
                    Cancel
                </a>
                <button type="submit" class="kt-btn kt-btn-primary">
                    Dispatch Message
                </button>
            </div>
        </form>
    </x-metronic.card>
</div>
@endsection

