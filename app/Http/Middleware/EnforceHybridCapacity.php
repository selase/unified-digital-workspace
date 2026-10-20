<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Tenancy\HybridCapacityService;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class EnforceHybridCapacity
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly HybridCapacityService $capacity,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->getTenant();
        if (! $tenant || $this->capacity->limit($tenant, 'files') === null) {
            return $next($request);
        }

        $name = (string) $request->route()?->getName();
        $middleware = $request->route()?->gatherMiddleware() ?? [];
        $website = in_array('cms.website', $middleware, true)
            || str_starts_with($name, 'cms-core.public.')
            || str_starts_with($name, 'cms-core.website.')
            || (str_starts_with($name, 'site-') && (str_contains($name, '.public.') || str_contains($name, '.website.')))
            || $request->route()?->getActionName() === 'App\\Http\\Controllers\\SiteIconController@appleTouchIcon'
            || str_starts_with($name, 'site-icon.')
            || (str_starts_with($name, 'forms.public.')
                && \App\Modules\Forms\Models\FormDefinition::query()
                    ->where('tenant_id', $tenant->id)->where('slug', (string) $request->route('slug'))
                    ->where('target_audience', 'public')->exists());
        $cms = str_starts_with($name, 'forms.') || str_starts_with($name, 'api.forms.') || str_starts_with($name, 'cms-core.') || str_starts_with($name, 'api.cms-core.') || str_starts_with($name, 'site-') || $website;
        $media = $request->route('media');
        $publicMedia = $name === 'cms-core.media.serve' && $media
            && $media instanceof \App\Modules\CmsCore\Models\Media
            && ! $media->is_internal && $media->is_public !== false;

        $request->attributes->remove('hybrid.file_rollback');
        $deliver = fn (): Response => $this->meterResponse($tenant, $request, $next($request), $website || $publicMedia);
        try {
            return $cms && (! $request->isMethodSafe() || str_starts_with($name, 'site-icon.') || str_ends_with($name, '.thumb') || $request->route()?->getActionName() === 'App\\Http\\Controllers\\SiteIconController@appleTouchIcon')
                ? $this->capacity->databaseMutation($tenant, $deliver)
                : $deliver();
        } finally {
            $request->attributes->remove('hybrid.file_rollback');
        }
    }

    private function meterResponse(Tenant $tenant, Request $request, Response $response, bool $public): Response
    {
        if (! $public || $request->isMethod('HEAD')
            || $response->isEmpty() || $response->getStatusCode() >= 400) {
            return $response;
        }

        if ($response instanceof BinaryFileResponse) {
            $response->prepare($request);
            $this->capacity->reserveBandwidth($tenant, (int) $response->headers->get('Content-Length', (string) $response->getFile()->getSize()));

            return $response;
        }

        if ($response instanceof StreamedResponse) {
            // Materialize on disk before reserving, so a stream cannot send past
            // the limit or consume quota when its generator fails part way.
            $buffer = tmpfile();
            if ($buffer === false) {
                abort(503, 'Cannot stage website response.');
            }
            $level = ob_get_level();
            ob_start(function (string $chunk) use ($buffer): string {
                if (fwrite($buffer, $chunk) !== mb_strlen($chunk, '8bit')) {
                    throw new RuntimeException('Cannot stage website response.');
                }

                return '';
            }, 65536);
            try {
                $response->sendContent();
                while (ob_get_level() > $level) {
                    ob_end_flush();
                }
                $size = ftell($buffer);
                $this->capacity->reserveBandwidth($tenant, (int) $size);
                rewind($buffer);
            } catch (Throwable $exception) {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
                fclose($buffer);
                throw $exception;
            }

            $response->headers->set('Content-Length', (string) $size);

            return new StreamedResponse(function () use ($buffer): void {
                try {
                    fpassthru($buffer);
                } finally {
                    fclose($buffer);
                }
            }, $response->getStatusCode(), $response->headers->all());
        }

        $this->capacity->reserveBandwidth($tenant, mb_strlen((string) $response->getContent(), '8bit'));

        return $response;
    }
}
