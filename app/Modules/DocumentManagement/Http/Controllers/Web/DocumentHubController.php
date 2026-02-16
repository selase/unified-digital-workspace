<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\DocumentManagement\Models\Document;
use App\Modules\DocumentManagement\Models\DocumentQuiz;
use App\Modules\DocumentManagement\Models\DocumentQuizAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class DocumentHubController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('documents.view'), 403);

        $documents = Document::query()
            ->with('currentVersion')
            ->latest('updated_at')
            ->limit(10)
            ->get();

        $statusCounts = Document::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $attemptSummaries = DocumentQuizAttempt::query()
            ->selectRaw('quiz_id, count(*) as attempts_count, count(distinct user_id) as unique_users_count, avg(score) as average_score')
            ->groupBy('quiz_id')
            ->get()
            ->keyBy('quiz_id');

        $quizzes = DocumentQuiz::query()
            ->with('document:id,title')
            ->withCount('questions')
            ->withSum('questions as max_points', 'points')
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (DocumentQuiz $quiz) use ($attemptSummaries): array {
                $attemptSummary = $attemptSummaries->get($quiz->id);

                return [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'document_title' => $quiz->document?->title,
                    'questions_count' => (int) $quiz->questions_count,
                    'max_points' => max((int) ($quiz->max_points ?? 0), (int) $quiz->questions_count),
                    'attempts_count' => (int) ($attemptSummary?->attempts_count ?? 0),
                    'unique_users_count' => (int) ($attemptSummary?->unique_users_count ?? 0),
                    'average_score' => $attemptSummary?->average_score === null
                        ? null
                        : round((float) $attemptSummary->average_score, 2),
                ];
            });

        return view('document-management::index', [
            'documents' => $documents,
            'statusCounts' => $statusCounts,
            'quizzes' => $quizzes,
            'canManageQuizAnalytics' => (bool) $request->user()?->can('documents.manage_quizzes'),
        ]);
    }
}
