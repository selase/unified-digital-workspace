<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Exceptions\ModuleConflictException;
use App\Exceptions\ModuleDependencyException;
use App\Exceptions\ModuleEntitlementException;
use App\Exceptions\ModuleNotFoundException;
use App\Http\Controllers\Controller;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tenant-facing module catalog. Lists every catalog-eligible module
 * (`tier != 'custom'` and `tier != 'free'`) and classifies each as
 * enabled / available / locked from the active tenant's perspective.
 *
 * Tenant admins can toggle Available modules on/off; Locked modules
 * surface the missing features so the admin knows what to upgrade.
 */
final class ModuleCatalogController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ModuleManager $moduleManager
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('manage organization settings'), 403);

        $tenant = $this->tenantContext->getTenant();
        abort_if(! $tenant, 404);

        $modules = $this->moduleManager->discoverModules()
            ->reject(fn (array $module): bool => in_array(($module['tier'] ?? null), ['custom', 'free'], true))
            ->map(function (array $module) use ($tenant): array {
                $enabled = $this->moduleManager->isEnabledForTenant($module['slug'], $tenant);
                $canEnable = $this->moduleManager->canEnableForTenant($module, $tenant);
                $missing = $this->moduleManager->missingFeaturesForTenant($module['slug'], $tenant);

                $status = match (true) {
                    $enabled => 'enabled',
                    $canEnable => 'available',
                    default => 'locked',
                };

                return [
                    'slug' => $module['slug'],
                    'name' => $module['name'],
                    'description' => $module['description'] ?? '',
                    'tier' => $module['tier'] ?? 'standard',
                    'is_billable' => (bool) ($module['is_billable'] ?? false),
                    'status' => $status,
                    'missing_features' => $missing,
                    'depends_on' => $module['depends_on'] ?? [],
                ];
            })
            ->sortBy('name')
            ->values();

        return view('tenant.modules.catalog', [
            'modules' => $modules,
            'tenant' => $tenant,
        ]);
    }

    public function enable(Request $request, string $subdomain, string $slug): RedirectResponse
    {
        abort_if(! $request->user()?->can('manage organization settings'), 403);

        $tenant = $this->tenantContext->getTenant();
        abort_if(! $tenant, 404);

        try {
            $this->moduleManager->enableForTenant($slug, $tenant);

            return back()->with('status', 'success')->with('message', "Module '{$slug}' enabled.");
        } catch (ModuleEntitlementException $e) {
            return back()->with('status', 'error')->with('message', $e->getMessage());
        } catch (ModuleDependencyException|ModuleConflictException $e) {
            return back()->with('status', 'error')->with('message', $e->getMessage());
        } catch (ModuleNotFoundException) {
            abort(404);
        }
    }

    public function disable(Request $request, string $subdomain, string $slug): RedirectResponse
    {
        abort_if(! $request->user()?->can('manage organization settings'), 403);

        $tenant = $this->tenantContext->getTenant();
        abort_if(! $tenant, 404);

        try {
            $this->moduleManager->disableForTenant($slug, $tenant);

            return back()->with('status', 'success')->with('message', "Module '{$slug}' disabled.");
        } catch (ModuleDependencyException $e) {
            return back()->with('status', 'error')->with('message', $e->getMessage());
        } catch (ModuleNotFoundException) {
            abort(404);
        }
    }
}
