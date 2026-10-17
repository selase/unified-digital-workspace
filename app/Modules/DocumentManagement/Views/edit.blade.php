@extends('layouts.metronic.app')

@section('title', "Edit Controlled Document: {$document->title}")

@section('content')
    <div class="grid gap-5">
        <x-metronic.page-header
            :title="'Edit: ' . $document->title"
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Document Management', 'url' => route('document-management.index')],
                ['label' => 'Controlled Library', 'url' => route('document-management.documents.index')],
                ['label' => $document->slug, 'url' => route('document-management.documents.show', $document)],
                ['label' => 'Edit'],
            ]"
        >
            <x-slot:actions>
                <a href="{{ route('document-management.documents.show', $document) }}" class="kt-btn kt-btn-outline">
                    Cancel & Return
                </a>
            </x-slot:actions>
        </x-metronic.page-header>

        <form action="{{ route('document-management.documents.update', $document) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                {{-- Main Document Metadata --}}
                <div class="lg:col-span-8 flex flex-col gap-5">
                    <x-metronic.card title="Document Specifications" subtitle="Modify institutional metadata, category, and summary">
                        <div class="space-y-4">
                            <div>
                                <label for="title" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Document Title <span class="text-destructive">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="title"
                                    name="title"
                                    value="{{ old('title', $document->title) }}"
                                    required
                                    class="kt-input w-full text-foreground"
                                />
                                @error('title')
                                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="category" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                        Document Category <span class="text-destructive">*</span>
                                    </label>
                                    <select id="category" name="category" required class="kt-select w-full text-foreground">
                                        @foreach($categories as $category)
                                            <option value="{{ $category }}" @selected(old('category', $document->category) === $category)>
                                                {{ $category }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category')
                                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="slug" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                        Reference Code / Slug
                                    </label>
                                    <input
                                        type="text"
                                        id="slug"
                                        name="slug"
                                        value="{{ old('slug', $document->slug) }}"
                                        class="kt-input w-full text-foreground"
                                    />
                                    @error('slug')
                                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div>
                                <label for="description" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Executive Summary & Scope
                                </label>
                                <textarea
                                    id="description"
                                    name="description"
                                    rows="4"
                                    class="kt-textarea w-full text-foreground"
                                >{{ old('description', $document->description) }}</textarea>
                                @error('description')
                                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </x-metronic.card>

                    {{-- ISO Review & Governance Assignments --}}
                    <x-metronic.card title="ISO Review & Governance Workflow" subtitle="Designated reviewer, approver, and compliance dates">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="reviewer_id" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                        Designated Peer / Technical Reviewer
                                    </label>
                                    <select id="reviewer_id" name="reviewer_id" class="kt-select w-full text-foreground">
                                        <option value="">None Assigned...</option>
                                        @foreach($users as $u)
                                            <option value="{{ $u->uuid }}" @selected(old('reviewer_id', $document->reviewer_id) === $u->uuid)>
                                                {{ $u->name }} ({{ $u->email }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('reviewer_id')
                                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="approver_id" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                        Final Institutional Approver
                                    </label>
                                    <select id="approver_id" name="approver_id" class="kt-select w-full text-foreground">
                                        <option value="">None Assigned...</option>
                                        @foreach($users as $u)
                                            <option value="{{ $u->uuid }}" @selected(old('approver_id', $document->approver_id) === $u->uuid)>
                                                {{ $u->name }} ({{ $u->email }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('approver_id')
                                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="review_due_at" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                        Review Deadline Date
                                    </label>
                                    <input
                                        type="date"
                                        id="review_due_at"
                                        name="review_due_at"
                                        value="{{ old('review_due_at', $document->review_due_at?->format('Y-m-d')) }}"
                                        class="kt-input w-full text-foreground"
                                    />
                                    @error('review_due_at')
                                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="expires_at" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                        Expiration / Re-certification Date
                                    </label>
                                    <input
                                        type="date"
                                        id="expires_at"
                                        name="expires_at"
                                        value="{{ old('expires_at', $document->expires_at?->format('Y-m-d')) }}"
                                        class="kt-input w-full text-foreground"
                                    />
                                    @error('expires_at')
                                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            @if($activeDocuments->isNotEmpty())
                                <div>
                                    <label for="supersedes_id" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                        Supersedes Controlled Document
                                    </label>
                                    <select id="supersedes_id" name="supersedes_id" class="kt-select w-full text-foreground">
                                        <option value="">None</option>
                                        @foreach($activeDocuments as $ad)
                                            <option value="{{ $ad->id }}" @selected(old('supersedes_id', $document->supersedes_id) == $ad->id)>
                                                {{ $ad->title }} ({{ $ad->slug }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('supersedes_id')
                                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif
                        </div>
                    </x-metronic.card>
                </div>

                {{-- Side Controls --}}
                <div class="lg:col-span-4 flex flex-col gap-5">
                    <x-metronic.card title="Status & Save" subtitle="Lifecycle controls">
                        <div class="space-y-4">
                            <div>
                                <label for="status" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Current Lifecycle Status
                                </label>
                                <select id="status" name="status" class="kt-select w-full text-foreground">
                                    @foreach(\App\Modules\DocumentManagement\Models\Document::STATUS_LABELS as $val => $lbl)
                                        <option value="{{ $val }}" @selected(old('status', $document->status) === $val)>
                                            {{ $lbl }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="pt-4 border-t border-border flex flex-col gap-2">
                                <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">
                                    Save Document Changes
                                </button>
                                <a href="{{ route('document-management.documents.show', $document) }}" class="kt-btn kt-btn-outline w-full justify-center">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </x-metronic.card>
                </div>
            </div>
        </form>
    </div>
@endsection

