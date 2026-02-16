@extends('layouts.metronic.app')

@section('title', 'Document Quiz Analytics')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Document Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Quiz Analytics</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Manager-only analytics for learning and policy comprehension quizzes.</p>
                </div>
                <a href="{{ route('document-management.index') }}" class="kt-btn kt-btn-outline">
                    Back to Document Hub
                </a>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-4">
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Quizzes</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $totalQuizzes }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Attempts</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $totalAttempts }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Unique Participants</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $totalUniqueParticipants }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Weighted Average Score</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $weightedAverageScore ?? '—' }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-foreground">Quiz Performance</h2>
                <span class="text-xs text-muted-foreground">Sorted by attempt volume</span>
            </div>

            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Quiz</th>
                            <th>Document</th>
                            <th>Questions</th>
                            <th>Attempts</th>
                            <th>Participants</th>
                            <th>Avg Score</th>
                            <th>Pass Score</th>
                            <th>Pass Rate</th>
                            <th class="text-end">API Drilldown</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @forelse($quizAnalytics as $quiz)
                            <tr>
                                <td class="font-medium">{{ $quiz['title'] }}</td>
                                <td>{{ $quiz['document_title'] ?: '—' }}</td>
                                <td>{{ $quiz['questions_count'] }}</td>
                                <td>{{ $quiz['attempts_count'] }}</td>
                                <td>{{ $quiz['unique_users_count'] }}</td>
                                <td>{{ $quiz['average_score'] ?? '—' }}</td>
                                <td>
                                    @if($quiz['pass_score'] !== null)
                                        {{ $quiz['pass_score'] }} / {{ $quiz['max_points'] }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if($quiz['pass_rate'] !== null)
                                        <span class="kt-badge kt-badge-outline {{ $quiz['pass_rate'] >= 70 ? 'kt-badge-success' : 'kt-badge-warning' }}">
                                            {{ number_format((float) $quiz['pass_rate'], 2) }}%
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ $quiz['api_url'] }}" class="kt-btn kt-btn-sm kt-btn-outline">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-8 text-center text-muted-foreground">No quiz analytics available yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
