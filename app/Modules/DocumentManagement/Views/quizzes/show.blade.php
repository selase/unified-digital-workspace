@extends('layouts.metronic.app')

@section('title', "Take Quiz: {$quiz->title}")

@section('content')
    <div class="grid gap-5 max-w-4xl mx-auto">
        <x-metronic.page-header
            :title="$quiz->title"
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Document Management', 'url' => route('document-management.index')],
                ['label' => $document->title, 'url' => route('document-management.documents.show', $document)],
                ['label' => 'Comprehension Quiz'],
            ]"
        >
            <x-slot:actions>
                <a href="{{ route('document-management.documents.show', $document) }}" class="kt-btn kt-btn-outline">
                    Back to Document
                </a>
            </x-slot:actions>
        </x-metronic.page-header>

        {{-- Quiz Banner & Standards --}}
        <div class="kt-card p-5 border border-border bg-card">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-foreground">{{ $quiz->title }}</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Related Controlled Document: <a href="{{ route('document-management.documents.show', $document) }}" class="text-primary hover:underline font-semibold">{{ $document->title }}</a>
                    </p>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <span class="kt-badge kt-badge-primary">
                        {{ $quiz->questions->count() }} Questions
                    </span>
                    <span class="kt-badge kt-badge-outline">
                        Passing Standard: {{ $quiz->settings['pass_percentage'] ?? 80 }}%
                    </span>
                </div>
            </div>

            @if($attempts->isNotEmpty())
                <div class="mt-4 pt-4 border-t border-border">
                    <span class="text-2xs uppercase tracking-wider font-semibold text-muted-foreground block mb-2">Your Previous Attempts</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach($attempts as $att)
                            <div class="px-2.5 py-1 rounded border border-border text-xs flex items-center gap-1.5 bg-muted/20">
                                <span class="font-bold text-foreground">Score: {{ $att->score }} pts</span>
                                <span class="text-2xs text-muted-foreground">({{ $att->completed_at?->format('d/m H:i') }})</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Quiz Attempt Form --}}
        <form action="{{ route('document-management.quizzes.attempt', $quiz) }}" method="POST">
            @csrf

            <div class="space-y-5">
                @foreach($quiz->questions as $index => $q)
                    <div class="kt-card p-5 border border-border bg-card space-y-3">
                        <div class="flex items-start justify-between gap-4">
                            <h3 class="text-sm font-semibold text-foreground">
                                <span class="text-primary font-bold mr-1">{{ $index + 1 }}.</span>
                                {{ $q->body }}
                            </h3>
                            <span class="text-2xs text-muted-foreground font-semibold shrink-0">{{ $q->points }} pt(s)</span>
                        </div>

                        <div class="space-y-2 pt-2 border-t border-border/50">
                            @foreach($q->options as $optIndex => $optionText)
                                <label class="flex items-start gap-3 p-2.5 rounded-lg border border-border/60 hover:bg-muted/10 cursor-pointer transition-colors">
                                    <input
                                        type="radio"
                                        name="responses[{{ $q->id }}]"
                                        value="{{ $optIndex }}"
                                        required
                                        class="mt-0.5 text-primary focus:ring-primary"
                                    />
                                    <span class="text-xs text-foreground leading-normal">{{ $optionText }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="flex items-center justify-between pt-4">
                    <a href="{{ route('document-management.documents.show', $document) }}" class="kt-btn kt-btn-outline">
                        Cancel
                    </a>
                    <button type="submit" class="kt-btn kt-btn-primary" onclick="return confirm('Submit your answers for assessment?');">
                        Submit Quiz Assessment
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

