@extends('layouts.metronic.app')

@section('title', 'Draft Memorandum')

@section('content')
    <x-metronic.page-header
        title="Draft Memorandum"
        subtitle="Compose an official internal institutional memo with auditable recipients and tracking."
        :breadcrumbs="[
            ['label' => 'Memos', 'url' => route('memos.index')],
            ['label' => 'New Memo'],
        ]"
    >
        <x-slot:actions>
            <a href="{{ route('memos.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">
                <i class="ki-filled ki-arrow-left"></i>
                Cancel
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    @if ($errors->any())
        <div class="mb-6 p-4 rounded-xl border border-red-200 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-950/20 dark:text-red-400">
            <h4 class="font-semibold text-sm mb-1">Please correct the following errors:</h4>
            <ul class="list-disc list-inside text-xs space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('memos.store') }}" class="grid gap-6">
        @csrf

        <div class="grid gap-6 lg:grid-cols-12">
            <!-- Main Content Area -->
            <div class="lg:col-span-8 flex flex-col gap-6">
                <x-metronic.card title="Memo Details" description="Provide the subject and body of this official correspondence.">
                    <div class="space-y-5">
                        <div>
                            <label for="subject" class="block text-sm font-medium text-foreground mb-1.5">
                                Subject <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="subject"
                                name="subject"
                                value="{{ old('subject') }}"
                                required
                                placeholder="e.g. IMPLEMENTATION OF CLINICAL ACCREDITATION PROTOCOLS 2026"
                                class="w-full px-4 py-2.5 rounded-lg border border-border bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                            />
                        </div>

                        <div>
                            <label for="body" class="block text-sm font-medium text-foreground mb-1.5">
                                Memo Content <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                id="body"
                                name="body"
                                rows="12"
                                required
                                placeholder="Enter memo directives, background, and instructions here..."
                                class="w-full px-4 py-3 rounded-lg border border-border bg-background text-foreground text-sm font-normal focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors leading-relaxed"
                            >{{ old('body') }}</textarea>
                            <p class="mt-1 text-xs text-muted-foreground">State directives clearly. Minutes can be appended after dispatch.</p>
                        </div>
                    </div>
                </x-metronic.card>
            </div>

            <!-- Sidebar / Recipients Selector -->
            <div class="lg:col-span-4 flex flex-col gap-6">
                <x-metronic.card title="Addressees & Recipients" description="Select users who must receive and act on this memo.">
                    <div class="space-y-4">
                        <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Select Addressees (TO):</p>

                        <div class="max-h-72 overflow-y-auto divide-y divide-border border border-border rounded-lg bg-muted/10 p-2">
                            @forelse($users as $index => $u)
                                <label class="flex items-center gap-3 p-2 rounded-md hover:bg-muted/40 cursor-pointer transition-colors">
                                    <input
                                        type="checkbox"
                                        name="recipients[{{ $index }}][id]"
                                        value="{{ $u->id }}"
                                        @checked(old("recipients.{$index}.id") == $u->id || ($loop->first && !old('recipients')) && $u->uuid !== auth()->user()?->uuid)
                                        class="rounded border-border text-primary focus:ring-primary size-4"
                                    />
                                    <input type="hidden" name="recipients[{{ $index }}][type]" value="user" />
                                    <input type="hidden" name="recipients[{{ $index }}][role]" value="to" />
                                    <div class="flex flex-col min-w-0">
                                        <span class="text-sm font-medium text-foreground truncate">
                                            {{ $u->displayName() ?: $u->first_name . ' ' . $u->last_name }}
                                        </span>
                                        <span class="text-2xs text-muted-foreground truncate">{{ $u->email }}</span>
                                    </div>
                                </label>
                            @empty
                                <p class="text-xs text-muted-foreground p-3">No active users found.</p>
                            @endforelse
                        </div>

                        <div>
                            <label for="cc_user_ids" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                Carbon Copy (CC — Optional)
                            </label>
                            <select id="cc_user_ids" name="cc_user_ids[]" multiple class="kt-select w-full h-24">
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->displayName() ?: $u->first_name . ' ' . $u->last_name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="bcc_user_ids" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                Blind Carbon Copy (BCC — Hidden from To/CC)
                            </label>
                            <select id="bcc_user_ids" name="bcc_user_ids[]" multiple class="kt-select w-full h-24">
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->displayName() ?: $u->first_name . ' ' . $u->last_name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="pt-4 border-t border-border flex flex-col gap-3">
                            <button
                                type="submit"
                                name="action"
                                value="send"
                                class="w-full kt-btn kt-btn-primary flex items-center justify-center gap-2"
                            >
                                <i class="ki-filled ki-send"></i>
                                Sign & Dispatch Immediately
                            </button>
                            <button
                                type="submit"
                                name="action"
                                value="draft"
                                class="w-full kt-btn kt-btn-outline flex items-center justify-center gap-2"
                            >
                                <i class="ki-filled ki-file-down"></i>
                                Save as Draft
                            </button>
                        </div>
                    </div>
                </x-metronic.card>
            </div>
        </div>
    </form>
@endsection

