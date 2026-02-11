<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\DocumentManagement\Http\Requests\DocumentQuizAttemptStoreRequest;
use App\Modules\DocumentManagement\Http\Resources\DocumentQuizAttemptResource;
use App\Modules\DocumentManagement\Models\Document;
use App\Modules\DocumentManagement\Models\DocumentQuiz;
use App\Modules\DocumentManagement\Models\DocumentQuizAttempt;
use Illuminate\Http\JsonResponse;

final class DocumentQuizAttemptController extends Controller
{
    public function store(DocumentQuizAttemptStoreRequest $request, DocumentQuiz $quiz): JsonResponse
    {
        $document = Document::findOrFail($quiz->document_id);
        $this->ensureVisible($document, (string) $request->user()?->uuid);

        $score = 0;
        $responses = $request->input('responses', []);

        $questions = $quiz->questions()->get()->keyBy('id');

        foreach ($responses as $response) {
            $questionId = $response['question_id'] ?? null;
            $answer = $response['answer'] ?? null;

            if ($questionId && $questions->has($questionId)) {
                $question = $questions->get($questionId);
                if ($question->correct_option !== null && $answer === $question->correct_option) {
                    $score += (int) ($question->points ?? 1);
                }
            }
        }

        $attempt = DocumentQuizAttempt::create([
            'tenant_id' => $quiz->tenant_id,
            'quiz_id' => $quiz->id,
            'user_id' => (string) $request->user()?->uuid,
            'responses' => $responses,
            'score' => $score,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $document->audits()->create([
            'tenant_id' => $document->tenant_id,
            'user_id' => (string) $request->user()?->uuid,
            'event' => 'quiz_attempt',
            'metadata' => [
                'quiz_id' => $quiz->id,
                'score' => $score,
            ],
            'created_at' => now(),
        ]);

        return (new DocumentQuizAttemptResource($attempt))->response()->setStatusCode(201);
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
