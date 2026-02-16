<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\DocumentManagement\Models\DocumentQuiz;
use App\Modules\DocumentManagement\Models\DocumentQuizAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class DocumentQuizAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('documents.manage_quizzes'), 403);

        $quizzes = DocumentQuiz::query()
            ->with('document:id,title')
            ->withCount('questions')
            ->withSum('questions as max_points', 'points')
            ->latest()
            ->get();

        $attemptSummaries = DocumentQuizAttempt::query()
            ->selectRaw('quiz_id, count(*) as attempts_count, count(distinct user_id) as unique_users_count, avg(score) as average_score, min(score) as min_score, max(score) as max_score')
            ->groupBy('quiz_id')
            ->get()
            ->keyBy('quiz_id');

        $scoresByQuiz = DocumentQuizAttempt::query()
            ->select('quiz_id', 'score')
            ->whereNotNull('score')
            ->get()
            ->groupBy('quiz_id');

        $quizAnalytics = $quizzes->map(function (DocumentQuiz $quiz) use ($attemptSummaries, $scoresByQuiz): array {
            $summary = $attemptSummaries->get($quiz->id);
            $maxPoints = max((int) ($quiz->max_points ?? 0), (int) $quiz->questions_count);
            $settings = $quiz->settings ?? [];
            $passScore = $settings['pass_score'] ?? null;

            if ($passScore === null && isset($settings['pass_percentage']) && $maxPoints > 0) {
                $passScore = (int) ceil($maxPoints * ((float) $settings['pass_percentage'] / 100));
            }

            $scores = $scoresByQuiz->get($quiz->id, collect());
            $passCount = null;
            $passRate = null;

            if ($passScore !== null) {
                $passCount = $scores
                    ->where('score', '>=', $passScore)
                    ->count();

                $totalAttempts = (int) ($summary?->attempts_count ?? 0);
                $passRate = $totalAttempts > 0
                    ? round(($passCount / $totalAttempts) * 100, 2)
                    : 0.0;
            }

            return [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'document_title' => $quiz->document?->title,
                'questions_count' => (int) $quiz->questions_count,
                'max_points' => $maxPoints,
                'attempts_count' => (int) ($summary?->attempts_count ?? 0),
                'unique_users_count' => (int) ($summary?->unique_users_count ?? 0),
                'average_score' => $summary?->average_score === null ? null : round((float) $summary->average_score, 2),
                'min_score' => $summary?->min_score,
                'max_score' => $summary?->max_score,
                'pass_score' => $passScore,
                'pass_count' => $passCount,
                'pass_rate' => $passRate,
                'api_url' => route('api.document-management.v1.documents.quizzes.analytics', [
                    'document' => $quiz->document_id,
                    'quiz' => $quiz->id,
                ]),
            ];
        })->sortByDesc('attempts_count')->values();

        $attemptTotals = $quizAnalytics->sum('attempts_count');
        $weightedScoreBase = $quizAnalytics->sum(fn (array $quiz): float => ((float) ($quiz['average_score'] ?? 0.0)) * (int) $quiz['attempts_count']);
        $weightedAverageScore = $attemptTotals > 0 ? round($weightedScoreBase / $attemptTotals, 2) : null;

        return view('document-management::analytics', [
            'quizAnalytics' => $quizAnalytics,
            'totalQuizzes' => $quizzes->count(),
            'totalAttempts' => $attemptTotals,
            'totalUniqueParticipants' => $quizAnalytics->sum('unique_users_count'),
            'weightedAverageScore' => $weightedAverageScore,
        ]);
    }
}
