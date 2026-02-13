<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\DocumentManagement\Http\Requests\DocumentQuizStoreRequest;
use App\Modules\DocumentManagement\Http\Resources\DocumentQuizResource;
use App\Modules\DocumentManagement\Models\Document;
use App\Modules\DocumentManagement\Models\DocumentQuiz;
use App\Modules\DocumentManagement\Models\DocumentQuizAttempt;
use App\Modules\DocumentManagement\Models\DocumentQuizQuestion;
use Illuminate\Http\JsonResponse;

final class DocumentQuizController extends Controller
{
    public function store(DocumentQuizStoreRequest $request, Document $document): JsonResponse
    {
        $data = $request->validated();
        $this->ensureVisible($document, (string) $request->user()?->uuid);

        $quiz = DocumentQuiz::create([
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'settings' => $data['settings'] ?? [],
        ]);

        foreach ($data['questions'] ?? [] as $questionData) {
            DocumentQuizQuestion::create([
                'tenant_id' => $document->tenant_id,
                'quiz_id' => $quiz->id,
                'body' => $questionData['body'],
                'options' => $questionData['options'],
                'correct_option' => $questionData['correct_option'] ?? null,
                'points' => $questionData['points'] ?? 1,
                'sort_order' => $questionData['sort_order'] ?? 0,
            ]);
        }

        return (new DocumentQuizResource($quiz->load('questions')))->response()->setStatusCode(201);
    }

    public function show(Document $document, DocumentQuiz $quiz): DocumentQuizResource
    {
        $this->ensureVisible($document, (string) request()->user()?->uuid);

        if ($quiz->document_id !== $document->id) {
            abort(404);
        }

        return new DocumentQuizResource($quiz->load('questions'));
    }

    public function analytics(Document $document, DocumentQuiz $quiz): JsonResponse
    {
        abort_if(! request()->user()?->can('documents.manage_quizzes'), 403);

        $this->ensureVisible($document, (string) request()->user()?->uuid);

        if ($quiz->document_id !== $document->id) {
            abort(404);
        }

        $quiz->load('questions');

        $attemptsQuery = DocumentQuizAttempt::query()->where('quiz_id', $quiz->id);
        $attemptsCount = $attemptsQuery->count();
        $uniqueUsers = (clone $attemptsQuery)->distinct('user_id')->count('user_id');
        $averageScore = (clone $attemptsQuery)->avg('score');
        $minScore = (clone $attemptsQuery)->min('score');
        $maxScore = (clone $attemptsQuery)->max('score');

        $maxPossibleScore = $quiz->questions->sum(fn (DocumentQuizQuestion $question): int => (int) ($question->points ?? 1));

        $settings = $quiz->settings ?? [];
        $passScore = $settings['pass_score'] ?? null;

        if ($passScore === null && isset($settings['pass_percentage']) && $maxPossibleScore > 0) {
            $passScore = (int) ceil($maxPossibleScore * ((float) $settings['pass_percentage'] / 100));
        }

        $passCount = null;
        $passRate = null;

        if ($passScore !== null) {
            $passCount = (clone $attemptsQuery)->where('score', '>=', $passScore)->count();
            $passRate = $attemptsCount > 0 ? round(($passCount / $attemptsCount) * 100, 2) : 0.0;
        }

        $questionStats = $quiz->questions->mapWithKeys(function (DocumentQuizQuestion $question): array {
            return [
                $question->id => [
                    'id' => $question->id,
                    'body' => $question->body,
                    'points' => (int) ($question->points ?? 1),
                    'total_responses' => 0,
                    'correct_count' => 0,
                    'incorrect_count' => 0,
                    'correct_rate' => null,
                    'has_answer_key' => $question->correct_option !== null,
                ],
            ];
        });

        $attempts = $attemptsQuery->get(['responses']);

        foreach ($attempts as $attempt) {
            foreach ($attempt->responses ?? [] as $response) {
                $questionId = (int) ($response['question_id'] ?? 0);

                if (! $questionStats->has($questionId)) {
                    continue;
                }

                $entry = $questionStats->get($questionId);
                $entry['total_responses']++;

                if ($entry['has_answer_key']) {
                    $question = $quiz->questions->firstWhere('id', $questionId);
                    $correctOption = $question?->correct_option;

                    if ($correctOption !== null && (string) ($response['answer'] ?? '') === (string) $correctOption) {
                        $entry['correct_count']++;
                    } else {
                        $entry['incorrect_count']++;
                    }
                }

                $questionStats->put($questionId, $entry);
            }
        }

        $questionStats = $questionStats->map(function (array $entry): array {
            if (! $entry['has_answer_key']) {
                $entry['correct_count'] = null;
                $entry['incorrect_count'] = null;
                $entry['correct_rate'] = null;
            } else {
                $entry['correct_rate'] = $entry['total_responses'] > 0
                    ? round(($entry['correct_count'] / $entry['total_responses']) * 100, 2)
                    : 0.0;
            }

            unset($entry['has_answer_key']);

            return $entry;
        })->values();

        return response()->json([
            'data' => [
                'quiz_id' => $quiz->id,
                'document_id' => $document->id,
                'question_count' => $quiz->questions->count(),
                'max_score' => $maxPossibleScore,
                'attempts' => [
                    'total' => $attemptsCount,
                    'unique_users' => $uniqueUsers,
                    'average_score' => $averageScore === null ? null : round((float) $averageScore, 2),
                    'min_score' => $minScore,
                    'max_score' => $maxScore,
                    'pass_score' => $passScore,
                    'pass_rate' => $passRate,
                    'pass_count' => $passCount,
                ],
                'questions' => $questionStats,
            ],
        ]);
    }

    private function ensureVisible(Document $document, int|string $userId): void
    {
        $visible = Document::query()
            ->visibleTo($userId)
            ->where('id', $document->id)
            ->exists();

        abort_if(! $visible, 403);
    }
}
