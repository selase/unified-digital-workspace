<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\DocumentManagement\Models\DocumentAudit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class DocumentAuditLibraryController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('documents.audit.view'), 403);

        $audits = DocumentAudit::query()
            ->with('document:id,title')
            ->latest('created_at')
            ->paginate(20);

        return view('document-management::audits', [
            'audits' => $audits,
        ]);
    }
}
