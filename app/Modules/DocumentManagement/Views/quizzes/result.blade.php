@extends('layouts.metronic.app')

@section('title', "Quiz Result: {$quiz->title}")

@section('content')
    <div class="grid gap-5 max-w-3xl mx-auto">
        <x-metronic.page-header
            :title="'Assessment Result: ' . $quiz->title"
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Document Management', 'url' => route('document-management.index')],
                ['label' => $document->title, 'url' => route('document-management.documents.show', $document)],
                ['label' => 'Result'],
            ]"
        >
            <x-slot:actions>
                <a href="{{ route('document-management.documents.show', $document) }}" class="kt-btn kt-btn-outline">
                    Back to Document
                </a>
            </x-slot:actions>
        </x-metronic.page-header>

        {{-- Score Banner --}}
        <div class="kt-card p-6 border text-center {{ $passed ? 'bg-success/10 border-success/30' : 'bg-destructive/10 border-destructive/30' }}">
            <div class="inline-flex p-3 rounded-full mb-3 {{ $passed ? 'bg-success/20 text-success' : 'bg-destructive/20 text-destructive' }}">
                @if($passed)
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                @else
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                @endif
            </div>

            <h2 class="text-xl font-bold {{ $passed ? 'text-success' : 'text-destructive' }}">
                {{ $passed ? 'Assessment Passed Successfully' : 'Assessment Threshold Not Met' }}
            </h2>

            <p class="text-xs text-muted-foreground mt-1 max-w-md mx-auto">
                {{ $passed ? 'You have demonstrated clinical and operational comprehension of this controlled standard.' : 'The required passing standard for this policy was not achieved. Please review the document and retake the assessment.' }}
            </p>

            <div class="flex justify-center items-center gap-6 mt-5 pt-4 border-t border-border/40 text-xs">
                <div>
                    <span class="text-2xs text-muted-foreground uppercase tracking-wider block font-semibold">Your Score</span>
                    <span class="text-xl font-bold text-foreground">{{ $score }} / {{ $maxScore }} pts</span>
                </div>
                <div class="h-8 w-px bg-border/60"></div>
                <div>
                    <span class="text-2xs text-muted-foreground uppercase tracking-wider block font-semibold">Percentage</span>
                    <span class="text-xl font-bold {{ $passed ? 'text-success' : 'text-destructive' }}">{{ $percentage }}%</span>
                </div>
                <div class="h-8 w-px bg-border/60"></div>
                <div>
                    <span class="text-2xs text-muted-foreground uppercase tracking-wider block font-semibold">Required Standard</span>
                    <span class="text-xl font-bold text-foreground">{{ $passPercentage }}%</span>
                </div>
            </div>
        </div>

        {{-- Question Response Review --}}
        <x-metronic.card title="Question Performance Summary" subtitle="Review your recorded answers against key standards">
            <div class="space-y-3">
                @foreach($attempt->responses ?? [] as $idx => $r)
                    <div class="p-3 rounded-lg border flex items-center justify-between text-xs {{ ($r['is_correct'] ?? false) ? 'bg-success/5 border-success/20' : 'bg-destructive/5 border-destructive/20' }}">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-foreground">Question {{ $idx + 1 }}</span>
                            <span class="text-muted-foreground">&bull;</span>
                            <span class="text-muted-foreground text-2xs">Recorded Choice: Option {{ is_numeric($r['selected_answer'] ?? null) ? chr(65 + (int) $r['selected_answer']) : 'None' }}</span>
                        </div>

                        <div>
                            @if($r['is_correct'] ?? false)
                                <span class="kt-badge kt-badge-xs kt-badge-success">Correct (+1)</span>
                            @else
                                <span class="kt-badge kt-badge-xs kt-badge-destructive">Incorrect (0)</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-metronic.card>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('document-management.quizzes.show', $quiz) }}" class="kt-btn kt-btn-outline">
                Retake Assessment
            </a>
            <a href="{{ route('document-management.documents.show', $document) }}" class="kt-btn kt-btn-primary">
                Return to Controlled Document
            </a>
        </div>
    </div>
@endsection

