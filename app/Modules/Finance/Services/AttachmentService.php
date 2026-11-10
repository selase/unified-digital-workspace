<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinAttachment;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Keeps receipts and supplier documents with the bill or expense they belong to, on the organisation's own storage. */
final class AttachmentService
{
    public const TYPES = ['bill', 'expense'];

    public const MAX_KB = 5120;

    /** @var list<string> */
    private const ALLOWED = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public function attach(string $tenantId, string $type, int $id, UploadedFile $file, ?string $userId = null): FinAttachment
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new DomainException('Attachments can only be added to bills and expenses.');
        }

        $extension = mb_strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, self::ALLOWED, true) || $file->getSize() > self::MAX_KB * 1024) {
            throw new DomainException('Attach a PDF or image (JPG, PNG, WebP) of 5 MB or less.');
        }

        $path = Storage::disk('tenant')->putFileAs('finance/'.$tenantId.'/'.$type, $file, Str::uuid().'.'.$extension);

        return FinAttachment::query()->create([
            'tenant_id' => $tenantId,
            'attachable_type' => $type,
            'attachable_id' => $id,
            'name' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
            'path' => (string) $path,
            'mime' => $extension === 'pdf' ? 'application/pdf' : 'image/'.($extension === 'jpg' ? 'jpeg' : $extension),
            'size' => (int) $file->getSize(),
            'uploaded_by' => $userId,
        ]);
    }

    public function remove(FinAttachment $attachment): void
    {
        Storage::disk('tenant')->delete($attachment->path);
        $attachment->delete();
    }
}
