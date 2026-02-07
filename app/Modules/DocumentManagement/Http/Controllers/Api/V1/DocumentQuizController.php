<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\DocumentManagement\Http\Requests\DocumentQuizStoreRequest;
use App\Modules\DocumentManagement\Http\Resources\DocumentQuizResource;
use App\Modules\DocumentManagement\Models\Document;
use App\Modules\DocumentManagement\Models\DocumentQuiz;
use App\Modules\DocumentManagement\Models\DocumentQuizQuestion;
use Illuminate\Http\JsonResponse;

final class DocumentQuizController extends Controller
{
    public function store(DocumentQuizStoreRequest $request, Document $document): JsonResponse
    {
        $data = $request->validated();

        $quiz = DocumentQuiz::create([
            'document_id' => $document->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'settings' => $data['settings'] ?? [],
        ]);

        foreach ($data['questions'] ?? [] as $questionData) {
            DocumentQuizQuestion::create([
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
        if ($quiz->document_id !== $document->id) {
            abort(404);
        }

        return new DocumentQuizResource($quiz->load('questions'));
    }
}
