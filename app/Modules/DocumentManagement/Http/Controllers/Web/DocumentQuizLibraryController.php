<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\DocumentManagement\Models\DocumentQuiz;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class DocumentQuizLibraryController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('documents.view'), 403);

        $quizzes = DocumentQuiz::query()
            ->with('document:id,title')
            ->withCount(['questions', 'attempts'])
            ->latest()
            ->paginate(15);

        return view('document-management::quizzes', [
            'quizzes' => $quizzes,
        ]);
    }
}
