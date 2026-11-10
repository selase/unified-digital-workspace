<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\FinAttachment;
use App\Modules\Finance\Models\FinBill;
use App\Modules\Finance\Models\FinExpense;
use App\Modules\Finance\Services\AttachmentService;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AttachmentController extends Controller
{
    use ResolvesFinanceTenant;

    public function store(Request $request, AttachmentService $attachments, string $type, string $uuid): RedirectResponse
    {
        $permission = $type === 'expense' ? 'finance.expenses.manage' : 'finance.bills.manage';
        $tenantId = $this->allow($request, $permission);
        $request->validate(['file' => ['required', 'file', 'max:'.AttachmentService::MAX_KB]], ['file.required' => 'Choose a file to attach.', 'file.max' => 'The file must be 5 MB or less.']);

        $parent = match ($type) {
            'bill' => FinBill::query()->where('tenant_id', $tenantId)->where('uuid', $uuid)->firstOrFail(),
            'expense' => FinExpense::query()->where('tenant_id', $tenantId)->where('uuid', $uuid)->firstOrFail(),
            default => abort(404),
        };

        try {
            $attachments->attach($tenantId, $type, $parent->id, $request->file('file'), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Attached.');
    }

    public function download(Request $request, string $attachment): StreamedResponse
    {
        $tenantId = $this->allow($request, 'finance.view');
        $model = FinAttachment::query()->where('tenant_id', $tenantId)->where('uuid', $attachment)->firstOrFail();
        abort_unless(Storage::disk('tenant')->exists($model->path), 404);

        return Storage::disk('tenant')->download($model->path, $model->name, ['Content-Type' => $model->mime]);
    }

    public function destroy(Request $request, AttachmentService $attachments, string $attachment): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $model = FinAttachment::query()->where('tenant_id', $tenantId)->where('uuid', $attachment)->firstOrFail();
        $this->allow($request, $model->attachable_type === 'expense' ? 'finance.expenses.manage' : 'finance.bills.manage');
        $attachments->remove($model);

        return back()->with('success', 'Attachment removed.');
    }
}
