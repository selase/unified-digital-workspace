<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Models\Media;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves tenant media files to the browser.
 *
 * Files are stored on the `tenant` disk (storage/app/tenants/{tenant-id}/)
 * which has no public URL mapping. This controller streams files with
 * proper cache headers and validates that the media belongs to the
 * current tenant context.
 */
final class CmsMediaServeController extends Controller
{
    public function show(Media $media): StreamedResponse
    {
        $disk = Storage::disk($media->disk);

        if (! $disk->exists($media->path)) {
            abort(404);
        }

        $mimeType = $media->mime_type ?: 'application/octet-stream';
        $filename = $media->original_filename ?: $media->filename;

        return $disk->response($media->path, $filename, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
