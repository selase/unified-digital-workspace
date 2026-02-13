<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class MetronicAssetController extends Controller
{
    public function __invoke(string $path): BinaryFileResponse
    {
        $basePath = realpath(base_path('metronic-tailwind-html-demos/dist/assets'));

        abort_unless(is_string($basePath), 404);

        $resolvedPath = realpath($basePath.DIRECTORY_SEPARATOR.mb_ltrim($path, '/'));

        if (! is_string($resolvedPath) || ! str_starts_with($resolvedPath, $basePath.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        if (! File::isFile($resolvedPath)) {
            abort(404);
        }

        $mimeType = File::mimeType($resolvedPath) ?? 'application/octet-stream';

        return response()->file($resolvedPath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
