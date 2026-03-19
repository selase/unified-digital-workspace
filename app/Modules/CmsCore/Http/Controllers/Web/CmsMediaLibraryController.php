<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Models\Media;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class CmsMediaLibraryController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('cms.media.view'), 403);

        $search = mb_trim((string) $request->string('search'));
        $type = (string) $request->string('type');

        $mediaItems = Media::query()
            ->with('uploadedBy:uuid,first_name,last_name,email')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('filename', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('original_filename', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', function ($query) use ($type): void {
                $query->where('mime_type', 'like', "{$type}/%");
            })
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('cms-core::media', [
            'mediaItems' => $mediaItems,
            'search' => $search,
            'type' => $type,
        ]);
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()?->can('cms.media.manage'), 403);

        return view('cms-core::media-upload');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(! $request->user()?->can('cms.media.manage'), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $path = $file->store('cms/media', 'public');
        $originalFilename = (string) $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getClientMimeType();
        $size = $file->getSize();
        $title = $request->string('title')->value() ?: pathinfo($originalFilename, PATHINFO_FILENAME);

        $dimensions = $this->extractDimensions($file);

        $media = Media::query()->create([
            'disk' => 'public',
            'path' => (string) $path,
            'original_filename' => $originalFilename,
            'filename' => basename((string) $path),
            'extension' => $extension === '' ? null : $extension,
            'mime_type' => $mimeType ?: 'application/octet-stream',
            'size_bytes' => $size ?: 0,
            'checksum_sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'alt_text' => $request->string('alt_text')->value() ?: null,
            'title' => $title !== '' ? $title : null,
            'description' => $request->string('description')->value() ?: null,
            'uploaded_by' => (string) $request->user()?->uuid,
            'source' => 'upload',
            'is_public' => $request->boolean('is_public', true),
        ]);

        return redirect()
            ->route('cms-core.media.show', $media)
            ->with('status', 'success')
            ->with('message', 'Media uploaded successfully.');
    }

    public function show(Request $request, Media $media): View
    {
        abort_if(! $request->user()?->can('cms.media.view'), 403);

        $media->load('uploadedBy:uuid,first_name,last_name,email');

        return view('cms-core::media-show', [
            'media' => $media,
        ]);
    }

    public function edit(Request $request, Media $media): View
    {
        abort_if(! $request->user()?->can('cms.media.manage'), 403);

        return view('cms-core::media-edit', [
            'media' => $media,
        ]);
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        abort_if(! $request->user()?->can('cms.media.manage'), 403);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:500'],
            'caption' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $media->fill($validated);
        $media->is_public = $request->boolean('is_public');
        $media->save();

        return redirect()
            ->route('cms-core.media.show', $media)
            ->with('status', 'success')
            ->with('message', 'Media metadata updated.');
    }

    /**
     * Inline upload for the TipTap editor — returns JSON with the image URL.
     */
    public function uploadInline(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_if(! $request->user()?->can('cms.media.manage'), 403);

        $request->validate([
            'file' => ['required', 'image', 'max:10240'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $path = $file->store('cms/inline', 'public');
        $originalFilename = (string) $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getClientMimeType();
        $size = $file->getSize();
        $title = pathinfo($originalFilename, PATHINFO_FILENAME);

        $dimensions = $this->extractDimensions($file);

        $media = Media::query()->create([
            'disk' => 'public',
            'path' => (string) $path,
            'original_filename' => $originalFilename,
            'filename' => basename((string) $path),
            'extension' => $extension === '' ? null : $extension,
            'mime_type' => $mimeType ?: 'image/jpeg',
            'size_bytes' => $size ?: 0,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'title' => $title !== '' ? $title : null,
            'uploaded_by' => (string) $request->user()?->uuid,
            'source' => 'editor',
            'is_public' => true,
        ]);

        $url = Storage::disk('tenant')->url($media->path);

        return response()->json([
            'url' => $url,
            'id' => $media->id,
            'alt' => $media->title,
        ]);
    }

    public function destroy(Request $request, Media $media): RedirectResponse
    {
        abort_if(! $request->user()?->can('cms.media.manage'), 403);

        Storage::disk($media->disk)->delete($media->path);
        $media->delete();

        return redirect()
            ->route('cms-core.media.index')
            ->with('status', 'success')
            ->with('message', 'Media deleted.');
    }

    /**
     * @return array{width: int|null, height: int|null}
     */
    private function extractDimensions(UploadedFile $file): array
    {
        $mimeType = $file->getClientMimeType() ?? '';

        if (! str_starts_with($mimeType, 'image/')) {
            return ['width' => null, 'height' => null];
        }

        $info = @getimagesize($file->getRealPath());

        if ($info === false) {
            return ['width' => null, 'height' => null];
        }

        return ['width' => $info[0], 'height' => $info[1]];
    }
}
