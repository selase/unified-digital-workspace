@extends('layouts.metronic.app')

@section('title', 'Document Quizzes')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Document Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Quiz Library</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Quizzes mapped to documents with live attempt and question counts.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a class="kt-btn kt-btn-outline" href="{{ route('document-management.index') }}">Back to Hub</a>
                    <a class="kt-btn kt-btn-primary" href="{{ route('document-management.analytics.index') }}">Open Analytics</a>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Quiz</th>
                            <th>Document</th>
                            <th>Questions</th>
                            <th>Attempts</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @forelse($quizzes as $quiz)
                            <tr>
                                <td>
                                    <p class="font-medium">{{ $quiz->title }}</p>
                                    <p class="text-xs text-muted-foreground">ID: {{ $quiz->id }}</p>
                                </td>
                                <td>{{ $quiz->document?->title ?: 'Unlinked' }}</td>
                                <td>{{ $quiz->questions_count }}</td>
                                <td>{{ $quiz->attempts_count }}</td>
                                <td>{{ $quiz->updated_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-8 text-center text-muted-foreground" colspan="5">No quizzes configured yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $quizzes->links() }}</div>
        </div>
    </section>
@endsection
