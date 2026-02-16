<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\DocumentManagement\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class DocumentLibraryController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('documents.view'), 403);

        $documents = Document::query()
            ->with('currentVersion')
            ->latest('updated_at')
            ->paginate(15);

        return view('document-management::documents', [
            'documents' => $documents,
        ]);
    }
}
