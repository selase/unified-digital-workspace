<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\DocumentManagement\Http\Requests\DocumentQuizAttemptStoreRequest;
use App\Modules\DocumentManagement\Http\Resources\DocumentQuizAttemptResource;
use App\Modules\DocumentManagement\Models\DocumentQuiz;
use App\Modules\DocumentManagement\Models\DocumentQuizAttempt;
use Illuminate\Http\JsonResponse;

final class DocumentQuizAttemptController extends Controller
{
    public function store(DocumentQuizAttemptStoreRequest $request, DocumentQuiz $quiz): JsonResponse
    {
        $attempt = DocumentQuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $request->user()?->id,
            'responses' => $request->input('responses'),
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        return (new DocumentQuizAttemptResource($attempt))->response()->setStatusCode(201);
    }
}
