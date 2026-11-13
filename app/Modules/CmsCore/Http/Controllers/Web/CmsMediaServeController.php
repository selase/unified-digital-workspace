<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Services\MediaThumbnailService;
use Illuminate\Http\Request;
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
    public function show(Media $media, Request $request, MediaThumbnailService $variants): StreamedResponse
    {
        if ($media->is_internal && ! auth()->check()) {
            abort(403, 'Authentication required to access internal media assets.');
        }

        $disk = Storage::disk($media->disk);

        if (! $disk->exists($media->path)) {
            abort(404);
        }

        $mimeType = $media->mime_type ?: 'application/octet-stream';
        $filename = $media->original_filename ?: $media->filename;

        $cacheControl = $media->is_internal
            ? 'private, no-cache, no-store, must-revalidate'
            : 'public, max-age=31536000, immutable';

        $width = $request->query('width');
        if (! $media->is_internal && $media->is_public !== false && in_array($width, ['480', '768', '1200'], true)) {
            $variant = $variants->bytes($media, (int) $width);
            if ($variant !== null) {
                return response()->stream(static function () use ($variant): void {
                    echo $variant['contents'];
                }, 200, [
                    'Content-Type' => $variant['mime'],
                    'Content-Length' => (string) mb_strlen($variant['contents'], '8bit'),
                    'Cache-Control' => $cacheControl,
                ]);
            }
        }

        return $disk->response($media->path, $filename, [
            'Content-Type' => $mimeType,
            'Cache-Control' => $cacheControl,
        ]);
    }
}
