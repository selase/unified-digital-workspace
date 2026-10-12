<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Services\CmsSitemapService;
use App\Modules\CmsCore\Services\CmsUrlService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CmsPublicSitemapController extends Controller
{
    public function __construct(
        private readonly CmsSitemapService $sitemap,
        private readonly CmsUrlService $cmsUrl,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = $this->tenantContext->getTenant();

        abort_if($tenant === null, 404);

        $xml = $this->sitemap->xml(
            (string) $tenant->id,
            $request->getSchemeAndHttpHost(),
            $this->cmsUrl->isWebsiteMode(),
        );

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
