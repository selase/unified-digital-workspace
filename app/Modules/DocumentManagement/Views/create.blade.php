@extends('layouts.metronic.app')

@section('title', 'Register Controlled Document')

@section('content')
    <div class="grid gap-5">
        <x-metronic.page-header
            title="Register Controlled Document"
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Document Management', 'url' => route('document-management.index')],
                ['label' => 'Controlled Library', 'url' => route('document-management.documents.index')],
                ['label' => 'Register'],
            ]"
        >
            <x-slot:actions>
                <a href="{{ route('document-management.documents.index') }}" class="kt-btn kt-btn-outline">
                    Back to Library
                </a>
            </x-slot:actions>
        </x-metronic.page-header>

        <form action="{{ route('document-management.documents.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                {{-- Main Document Metadata --}}
                <div class="lg:col-span-8 flex flex-col gap-5">
                    <x-metronic.card title="Document Specifications" subtitle="General metadata, institutional categorization, and ISO classification">
                        <div class="space-y-4">
                            <div>
                                <label for="title" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Document Title <span class="text-destructive">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="title"
                                    name="title"
                                    value="{{ old('title') }}"
                                    required
                                    placeholder="e.g. Infection Prevention and Control Clinical SOP"
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
                                        <option value="">Select Category...</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category }}" @selected(old('category') === $category)>
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
                                        Reference Code / Slug <span class="text-xs font-normal text-muted-foreground">(Optional)</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="slug"
                                        name="slug"
                                        value="{{ old('slug') }}"
                                        placeholder="e.g. sop-ipc-001 (auto-generated if empty)"
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
                                    placeholder="Describe the clinical/operational purpose, target departments, and standard applicability..."
                                    class="kt-textarea w-full text-foreground"
                                >{{ old('description') }}</textarea>
                                @error('description')
                                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </x-metronic.card>

                    <x-metronic.card title="Initial Controlled File (Version 1.0)" subtitle="Upload the initial approved or draft master document file">
                        <div class="space-y-4">
                            <div>
                                <label for="file" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Master Document File (.pdf, .docx, .xlsx, .pptx)
                                </label>
                                <input
                                    type="file"
                                    id="file"
                                    name="file"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt"
                                    class="kt-input w-full file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-primary-foreground hover:file:opacity-90"
                                />
                                <p class="mt-1 text-xs text-muted-foreground">Supported formats: PDF, DOCX, XLSX, PPTX. Maximum file size: 20MB. An SHA-256 cryptographic checksum will be generated on upload.</p>
                                @error('file')
                                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="notes" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Version 1.0 Release Notes
                                </label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    rows="2"
                                    placeholder="e.g. Initial draft release for departmental clinical review and quality sign-off."
                                    class="kt-textarea w-full text-foreground"
                                >{{ old('notes') }}</textarea>
                                @error('notes')
                                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </x-metronic.card>
                </div>

                {{-- Side Controls & Publishing Options --}}
                <div class="lg:col-span-4 flex flex-col gap-5">
                    <x-metronic.card title="Governance & Access" subtitle="Security controls and circulation boundary">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">
                                    Circulation Boundary
                                </label>
                                <div class="space-y-2">
                                    <label class="flex items-start gap-2.5 cursor-pointer">
                                        <input
                                            type="radio"
                                            name="visibility[tenant_wide]"
                                            value="1"
                                            checked
                                            class="mt-0.5 text-primary focus:ring-primary"
                                        />
                                        <div>
                                            <span class="text-sm font-medium text-foreground block">Institution-wide</span>
                                            <span class="text-xs text-muted-foreground block">Accessible to all verified employees and clinical staff in this workspace.</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <hr class="border-border" />

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">
                                    Publication Action
                                </label>
                                <div class="flex flex-col gap-2.5">
                                    <button
                                        type="submit"
                                        name="status"
                                        value="draft"
                                        class="kt-btn kt-btn-outline w-full justify-center"
                                    >
                                        Save as Controlled Draft
                                    </button>

                                    @can('documents.publish')
                                        <button
                                            type="submit"
                                            name="status"
                                            value="published"
                                            class="kt-btn kt-btn-primary w-full justify-center"
                                        >
                                            Publish to Institutional Catalog
                                        </button>
                                    @endcan
                                </div>
                                <p class="mt-2 text-xs text-center text-muted-foreground">Draft documents are visible for review and version bumping before final publication.</p>
                            </div>
                        </div>
                    </x-metronic.card>

                    <div class="rounded-xl border border-border/80 bg-muted/20 p-4 text-xs text-muted-foreground space-y-2">
                        <p class="font-semibold text-foreground flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            ISO 9001 / JCI Document Control
                        </p>
                        <p>Every uploaded version generates an immutable SHA-256 fingerprint, chronological audit history, and tracked release notes per hospital governance protocols.</p>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

