@extends('layouts.metronic.app')

@section('title', "Create Quiz: {$document->title}")

@section('content')
    <div class="grid gap-5">
        <x-metronic.page-header
            :title="'Create Quiz: ' . $document->title"
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Document Management', 'url' => route('document-management.index')],
                ['label' => 'Controlled Library', 'url' => route('document-management.documents.index')],
                ['label' => $document->slug, 'url' => route('document-management.documents.show', $document)],
                ['label' => 'Create Quiz'],
            ]"
        >
            <x-slot:actions>
                <a href="{{ route('document-management.documents.show', $document) }}" class="kt-btn kt-btn-outline">
                    Cancel
                </a>
            </x-slot:actions>
        </x-metronic.page-header>

        <form action="{{ route('document-management.documents.quizzes.store', $document) }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                <div class="lg:col-span-8 flex flex-col gap-5">
                    <x-metronic.card title="Quiz Configuration" subtitle="General quiz parameters and pass percentage">
                        <div class="space-y-4">
                            <div>
                                <label for="title" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Quiz Title <span class="text-destructive">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="title"
                                    name="title"
                                    value="{{ old('title', $document->title . ' - Comprehension Quiz') }}"
                                    required
                                    class="kt-input w-full text-foreground"
                                />
                                @error('title')
                                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="pass_percentage" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                        Pass Standard (%) <span class="text-destructive">*</span>
                                    </label>
                                    <input
                                        type="number"
                                        id="pass_percentage"
                                        name="pass_percentage"
                                        min="1"
                                        max="100"
                                        value="{{ old('pass_percentage', 80) }}"
                                        required
                                        class="kt-input w-full text-foreground"
                                    />
                                    @error('pass_percentage')
                                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="description" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                        Description
                                    </label>
                                    <input
                                        type="text"
                                        id="description"
                                        name="description"
                                        value="{{ old('description') }}"
                                        placeholder="e.g. Clinical comprehension check"
                                        class="kt-input w-full text-foreground"
                                    />
                                </div>
                            </div>
                        </div>
                    </x-metronic.card>

                    {{-- Questions Builder --}}
                    <x-metronic.card title="Questions & Answer Options" subtitle="Configure multiple-choice questions with correct answers">
                        <div class="space-y-6">
                            @for($i = 0; $i < 3; $i++)
                                <div class="p-4 rounded-xl border border-border bg-muted/10 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-bold text-xs uppercase tracking-wider text-foreground">Question {{ $i + 1 }}</h4>
                                        <span class="text-2xs text-muted-foreground">1 Point</span>
                                    </div>

                                    <div>
                                        <label class="block text-2xs font-semibold text-muted-foreground mb-1">Question Body *</label>
                                        <input
                                            type="text"
                                            name="questions[{{ $i }}][body]"
                                            value="{{ old("questions.{$i}.body") }}"
                                            placeholder="e.g. What is the mandatory hand hygiene contact time for alcohol rub?"
                                            {{ $i === 0 ? 'required' : '' }}
                                            class="kt-input w-full text-xs"
                                        />
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                                        <div>
                                            <label class="block text-2xs font-semibold text-muted-foreground mb-1">Option A *</label>
                                            <input
                                                type="text"
                                                name="questions[{{ $i }}][options][0]"
                                                value="{{ old("questions.{$i}.options.0") }}"
                                                placeholder="Option A text"
                                                {{ $i === 0 ? 'required' : '' }}
                                                class="kt-input w-full text-xs"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-2xs font-semibold text-muted-foreground mb-1">Option B *</label>
                                            <input
                                                type="text"
                                                name="questions[{{ $i }}][options][1]"
                                                value="{{ old("questions.{$i}.options.1") }}"
                                                placeholder="Option B text"
                                                {{ $i === 0 ? 'required' : '' }}
                                                class="kt-input w-full text-xs"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-2xs font-semibold text-muted-foreground mb-1">Option C (Optional)</label>
                                            <input
                                                type="text"
                                                name="questions[{{ $i }}][options][2]"
                                                value="{{ old("questions.{$i}.options.2") }}"
                                                placeholder="Option C text"
                                                class="kt-input w-full text-xs"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-2xs font-semibold text-muted-foreground mb-1">Correct Option Key *</label>
                                            <select name="questions[{{ $i }}][correct_option]" class="kt-select w-full text-xs" {{ $i === 0 ? 'required' : '' }}>
                                                <option value="0">Option A</option>
                                                <option value="1">Option B</option>
                                                <option value="2">Option C</option>
                                            </select>
                                        </div>
                                    </div>
                                    <input type="hidden" name="questions[{{ $i }}][points]" value="1" />
                                </div>
                            @endfor
                        </div>
                    </x-metronic.card>
                </div>

                <div class="lg:col-span-4 flex flex-col gap-5">
                    <x-metronic.card title="Save & Activate" subtitle="Publish quiz for staff">
                        <div class="space-y-4">
                            <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">
                                Save & Activate Quiz
                            </button>
                            <a href="{{ route('document-management.documents.show', $document) }}" class="kt-btn kt-btn-outline w-full justify-center">
                                Cancel
                            </a>
                        </div>
                    </x-metronic.card>
                </div>
            </div>
        </form>
    </div>
@endsection

