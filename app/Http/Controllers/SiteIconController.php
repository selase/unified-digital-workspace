<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CmsCore\Services\SiteIconService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves platform-specific favicons and the PWA manifest derived from the
 * active tenant's master site icon. Falls back to the platform default
 * when no tenant is resolved or no site icon has been configured.
 */
final class SiteIconController extends Controller
{
    public function __construct(
        private readonly SiteIconService $service
    ) {}

    /** Sizes are restricted to the catalog defined by the service. */
    public function serve(Request $request, int $size): Response
    {
        abort_unless(in_array($size, SiteIconService::SIZES, true), 404);

        $variant = $this->service->variantBytes($size);

        if (! $variant) {
            return $this->defaultIconResponse();
        }

        return $this->iconResponse($variant['contents'], $variant['mime']);
    }

    public function favicon(Request $request): Response
    {
        $variant = $this->service->variantBytes(32);

        if (! $variant) {
            return $this->defaultIconResponse('image/x-icon');
        }

        return $this->iconResponse($variant['contents'], 'image/x-icon');
    }

    public function appleTouchIcon(Request $request): Response
    {
        $variant = $this->service->variantBytes(180);

        if (! $variant) {
            return $this->defaultIconResponse();
        }

        return $this->iconResponse($variant['contents'], $variant['mime']);
    }

    public function manifest(Request $request): JsonResponse
    {
        return response()
            ->json($this->service->manifest(), 200, [
                'Content-Type' => 'application/manifest+json',
            ])
            ->setMaxAge(3600)
            ->setSharedMaxAge(3600);
    }

    private function iconResponse(string $contents, string $mime): Response
    {
        return response($contents, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=604800, immutable',
        ]);
    }

    private function defaultIconResponse(string $mime = 'image/x-icon'): Response
    {
        $path = public_path('assets/metronic/media/app/favicon.ico');

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
