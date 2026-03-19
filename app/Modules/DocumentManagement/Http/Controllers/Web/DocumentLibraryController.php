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

        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $documents = Document::query()
            ->with('currentVersion')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when(in_array($status, ['draft', 'published', 'archived'], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('document-management::documents', [
            'documents' => $documents,
            'search' => $search,
            'status' => $status,
        ]);
    }
}
