<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Tenant\NotificationCenterController;
use App\Livewire\Admin\TenantHealthCheck;
use App\Livewire\Admin\UserProfileSecurity;
use App\Livewire\PricingTable;
use App\Models\User;
use App\Modules\DocumentManagement\Http\Controllers\Web\DocumentLibraryController;
use App\Modules\DocumentManagement\Http\Controllers\Web\DocumentWebController;
use App\Modules\Forms\Http\Controllers\Web\FormsWebController;
use App\Modules\Forms\Models\FormDefinition;
use App\Modules\HrmsCore\Http\Controllers\Web\HrmsLeaveRequestController;
use App\Modules\IncidentManagement\Http\Controllers\Web\IncidentListController;
use App\Services\ModuleManager;
use App\Services\Tenancy\BillingAccessService;
use App\Services\Tenancy\BillingAgreementService;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Livewire\Mechanisms\ComponentRegistry;
use Livewire\Mechanisms\HandleComponents\Checksum;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EnforceBillingAccess
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly BillingAgreementService $agreements,
        private readonly BillingAccessService $access,
        private readonly ModuleManager $modules,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->publicBillingRoute($request)) {
            return $next($request);
        }
        $tenant = $this->context->getTenant();
        $user = $request->user();
        if ($tenant === null || $this->agreements->activeFor($tenant) === null) {
            return $next($request);
        }

        $route = $request->route();
        $middleware = $route?->gatherMiddleware() ?? [];
        $livewire = $request->routeIs('livewire.*') || $request->is('livewire/*');
        $servedMedia = $route?->getActionName() === 'App\\Modules\\CmsCore\\Http\\Controllers\\Web\\CmsMediaServeController@show'
            ? $route->parameter('media') : null;
        $privateMedia = $servedMedia instanceof \App\Modules\CmsCore\Models\Media
            && ($servedMedia->is_internal || $servedMedia->is_public === false);
        $protected = $privateMedia || $livewire || $request->attributes->get('auth_type') === 'api_key' || ($user instanceof User && ! $this->isPublicRoute($request));
        foreach ($middleware as $entry) {
            if (preg_match('/^(auth(?::|$)|auth_api_key$|'.preg_quote(Authenticate::class, '/').'(?::|$)|'.preg_quote(AuthenticateWithApiKey::class, '/').'$)/', $entry)) {
                $protected = true;
            }
        }

        if (! $protected) {
            return $next($request);
        }
        if ($user instanceof User && $user->isGlobalSuperAdmin()) {
            return $next($request);
        }
        if ($livewire && ! $user && $this->allowsLivewire($request, null, 'guest')) {
            return $next($request);
        }
        if ($this->safeAccountRoute($request)) {
            abort_if($user instanceof User && ! $this->access->isMember($tenant, $user), 403);

            return $next($request);
        }
        abort_unless($user instanceof User, 403, 'An assigned organisation account is required.');
        abort_unless($this->access->isMember($tenant, $user), 403, 'Organisation membership is required.');
        $level = $this->access->level($tenant, $user);
        if ($livewire) {
            abort_unless($this->allowsLivewire($request, $user, $level ?? 'unassigned'), 403, 'This component has not been approved for billing access.');
            if ($level !== null) {
                $this->admit($request, $level);
            }

            return $next($request);
        }

        abort_if($level === null, 403, 'Billing access has not been assigned.');
        if (in_array($level, ['website_editor', 'self_service'], true)
            && ($this->matches($request, 'dashboard', \App\Http\Controllers\Admin\DashboardController::class, '__invoke', 'GET')
                || $this->matches($request, 'tenant.dashboard', \App\Http\Controllers\Tenant\DashboardController::class, 'index', 'GET'))) {
            $landing = $this->access->workspaceLinks($tenant, $user)[0];

            return redirect()->route($landing['route'], $landing['params'] ?? []);
        }

        if ($level === 'operational'
            && $this->matches($request, 'dashboard', \App\Http\Controllers\Admin\DashboardController::class, '__invoke', 'GET')) {
            return redirect()->route('tenant.dashboard', ['subdomain' => $tenant->slug]);
        }

        $module = null;
        foreach ($middleware as $entry) {
            if (str_starts_with($entry, 'module:')) {
                $module = explode(':', $entry, 2)[1];
                abort_unless($this->modules->isEnabledForTenant($module, $tenant), 403, 'This module is not included in the billing agreement.');
            }
        }

        if ($module === null) {
            $action = $route?->getActionName() ?? '';
            foreach ($this->modules->all() as $manifest) {
                if (str_starts_with($action, 'App\\Modules\\'.$manifest['directory'].'\\')) {
                    $module = $manifest['slug'];
                    abort_unless($this->modules->isEnabledForTenant($module, $tenant), 403, 'This module is not included in the billing agreement.');
                    break;
                }
            }
        }

        if ($level === 'website_editor') {
            abort_unless(in_array($module, ['cms-core', 'forms'], true) || ($module !== null && $this->modules->isWebsiteExtensionForTenant($module, $tenant)), 403);
        } elseif ($level === 'self_service') {
            abort_unless($this->safeSelfServiceRoute($request), 403);
        }

        if ($route?->getActionName() === 'App\\Modules\\CmsCore\\Http\\Controllers\\Web\\CmsMediaServeController@show') {
            $media = $route->parameter('media');
            if ($media instanceof \App\Modules\CmsCore\Models\Media && ($media->is_internal || $media->is_public === false)) {
                abort_unless($user->can('cms.media.view'), 403);
            }
        }

        if ($request->routeIs('api.cms-core.*')
            || str_starts_with($route?->getActionName() ?? '', 'App\\Modules\\CmsCore\\Http\\Controllers\\Api\\')) {
            $permission = $this->cmsApiPermission($request);
            abort_unless($permission !== null && $user->can($permission), 403, 'The CMS API requires the matching resource permission.');
        }

        $this->admit($request, $level);

        return $next($request);
    }

    private function admit(Request $request, string $level): void
    {
        $request->attributes->set('hybrid_billing_access_allowed', true);
        $request->attributes->set('hybrid_billing_access_level', $level);
        $request->attributes->set('hybrid_billing_tenant_id', $this->context->getTenant()?->id);
        $request->attributes->set('hybrid_billing_user_id', $request->user()?->getAuthIdentifier());
        $request->attributes->set('hybrid_billing_month', now()->startOfMonth()->toDateString());
    }

    private function cmsApiPermission(Request $request): ?string
    {
        $resources = [
            'posts' => ['posts', 'Post'],
            'post-types' => ['post_types', 'PostType'],
            'post-meta' => ['posts', 'PostMeta'],
            'post-revisions' => ['posts', 'PostRevision'],
            'categories' => ['categories', 'Category'],
            'tags' => ['tags', 'Tag'],
            'media' => ['media', 'Media'],
            'media-variants' => ['media', 'MediaVariant'],
            'menus' => ['menus', 'Menu'],
            'menu-items' => ['menus', 'MenuItem'],
            'settings' => ['settings', 'Setting'],
        ];
        $readOnly = ['post-meta', 'post-revisions', 'media-variants', 'menu-items'];
        $controllerNamespace = 'App\\Modules\\CmsCore\\Http\\Controllers\\Api\\V1\\';
        $route = $request->route();
        if ($route === null) {
            return null;
        }
        foreach ($resources as $resource => [$permissionGroup, $model]) {
            $prefix = 'api.cms-core.v1.'.$resource.'.';
            $permission = null;
            foreach (['index', 'show'] as $method) {
                if ($this->matches($request, $prefix.$method, $controllerNamespace.'ReadOnlyResourceController', $method, 'GET')
                    && ($route->defaults['model'] ?? null) === 'App\\Modules\\CmsCore\\Models\\'.$model) {
                    $permission = 'cms.'.$permissionGroup.'.view';
                }
            }
            if (! in_array($resource, $readOnly, true)) {
                foreach (['store' => ['POST', 'create'], 'update' => ['PUT', 'update'], 'destroy' => ['DELETE', 'delete']] as $method => [$verb, $ability]) {
                    if ($method === 'destroy' && ! in_array($resource, ['posts', 'media'], true)) {
                        continue;
                    }
                    if ($this->matches($request, $prefix.$method, $controllerNamespace.$model.'Controller', $method, $verb)) {
                        $permission = 'cms.'.$permissionGroup.'.'.($resource === 'posts' ? $ability : 'manage');
                    }
                }
            }
            if ($permission !== null && in_array($permission, $this->modules->find('cms-core')['permissions'] ?? [], true)) {
                return $permission;
            }
        }

        return null;
    }

    private function matches(Request $request, string $name, string $controller, string $method, string $httpMethod): bool
    {
        return $request->routeIs($name) && in_array($request->method(), [$httpMethod, $httpMethod === 'GET' ? 'HEAD' : $httpMethod], true)
            && $request->route()?->getAction('uses') === $controller.'@'.$method;
    }

    private function safeAccountRoute(Request $request): bool
    {
        $billingActions = [
            \App\Http\Controllers\Billing\BillingController::class.'@index' => ['GET', 'HEAD'],
            \App\Http\Controllers\Billing\BillingController::class.'@pricing' => ['GET', 'HEAD'],
            \App\Http\Controllers\Billing\InvoiceController::class.'@index' => ['GET', 'HEAD'],
            \App\Http\Controllers\Billing\InvoiceController::class.'@show' => ['GET', 'HEAD'],
            \App\Http\Controllers\Billing\InvoiceController::class.'@download' => ['GET', 'HEAD'],
            \App\Http\Controllers\Tenant\BillingSettingsController::class.'@index' => ['GET', 'HEAD'],
            \App\Http\Controllers\Tenant\BillingSettingsController::class.'@update' => ['POST', 'PUT', 'PATCH'],
            \App\Http\Controllers\Billing\CheckoutController::class.'@store' => ['POST'],
            \App\Http\Controllers\Billing\InvoicePaymentController::class.'@checkout' => ['POST'],
        ];
        if ($request->user()?->can('core.settings.manage')
            && $request->routeIs('billing.*', 'tenant.billing.*', 'tenant.pricing', 'tenant.settings.billing.*')
            && in_array($request->method(), $billingActions[$request->route()?->getActionName() ?? ''] ?? [], true)) {
            return true;
        }
        $routes = [
            'logout' => [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy', 'POST'],
            'password.confirm' => [\App\Http\Controllers\Auth\ConfirmablePasswordController::class, 'show', 'GET'],
            'verification.notice' => [\App\Http\Controllers\Auth\EmailVerificationPromptController::class, '__invoke', 'GET'],
            'verification.verify' => [\App\Http\Controllers\Auth\VerifyEmailController::class, '__invoke', 'GET'],
            'verification.send' => [\App\Http\Controllers\Auth\EmailVerificationNotificationController::class, 'store', 'POST'],
            'two-factor.challenge' => [\App\Http\Controllers\Auth\TwoFactorChallengeController::class, 'show', 'GET'],
            'two-factor.challenge.store' => [\App\Http\Controllers\Auth\TwoFactorChallengeController::class, 'store', 'POST'],
        ];
        foreach ($routes as $name => [$controller, $method, $verb]) {
            if ($this->matches($request, $name, $controller, $method, $verb)) {
                return true;
            }
        }
        if ($request->method() === 'POST' && $request->route()?->uri() === 'confirm-password'
            && $request->route()->getActionName() === \App\Http\Controllers\Auth\ConfirmablePasswordController::class.'@store') {
            return true;
        }
        $profile = $request->route()?->parameter('user');

        return $this->matches($request, 'profile.index', ProfileController::class, 'index', 'GET')
            && $profile instanceof User && $profile->is($request->user());
    }

    private function publicBillingRoute(Request $request): bool
    {
        $controller = \App\Http\Controllers\Billing\InvoicePaymentPortalController::class;
        foreach (['show' => 'GET', 'pay' => 'POST', 'checkout' => 'GET', 'check' => 'POST', 'invoice' => 'GET', 'receipt' => 'GET', 'callback' => 'GET'] as $action => $method) {
            if ($this->matches($request, 'billing.payment.'.$action, $controller, $action, $method)) {
                return true;
            }
        }

        return false;
    }

    private function safeSelfServiceRoute(Request $request): bool
    {
        $routes = [
            'notifications.index' => [NotificationCenterController::class, 'index', 'GET'],
            'notifications.mark-read' => [NotificationCenterController::class, 'markAsRead', 'POST'],
            'notifications.mark-all-read' => [NotificationCenterController::class, 'markAllAsRead', 'POST'],
            'hrms-core.index' => [\App\Modules\HrmsCore\Http\Controllers\Web\HrmsHubController::class, 'index', 'GET'],
            'document-management.index' => [\App\Modules\DocumentManagement\Http\Controllers\Web\DocumentHubController::class, 'index', 'GET'],
            'incident-management.index' => [\App\Modules\IncidentManagement\Http\Controllers\Web\IncidentHubController::class, 'index', 'GET'],
            'hrms-core.leave-requests.index' => [HrmsLeaveRequestController::class, 'index', 'GET'],
            'hrms-core.leave-requests.create' => [HrmsLeaveRequestController::class, 'create', 'GET'],
            'hrms-core.leave-requests.store' => [HrmsLeaveRequestController::class, 'store', 'POST'],
            'document-management.documents.index' => [DocumentLibraryController::class, 'index', 'GET'],
            'document-management.documents.show' => [DocumentWebController::class, 'show', 'GET'],
            'document-management.documents.download' => [DocumentWebController::class, 'download', 'GET'],
            'document-management.documents.acknowledge' => [DocumentWebController::class, 'acknowledge', 'POST'],
            'incident-management.incidents.index' => [IncidentListController::class, 'index', 'GET'],
            'incident-management.incidents.create' => [IncidentListController::class, 'create', 'GET'],
            'incident-management.incidents.store' => [IncidentListController::class, 'store', 'POST'],
            'incident-management.incidents.show' => [IncidentListController::class, 'show', 'GET'],
            'forms.index' => [FormsWebController::class, 'index', 'GET'],
            'forms.show' => [FormsWebController::class, 'show', 'GET'],
            'forms.public.show' => [FormsWebController::class, 'publicShow', 'GET'],
            'forms.public.submit' => [FormsWebController::class, 'publicSubmit', 'POST'],
            'forms.submissions.show' => [FormsWebController::class, 'showSubmission', 'GET'],
        ];
        foreach ($routes as $name => [$controller, $method, $verb]) {
            if ($this->matches($request, $name, $controller, $method, $verb)) {
                return true;
            }
        }

        return false;
    }

    private function isPublicRoute(Request $request): bool
    {
        $action = $request->route()?->getActionName() ?? '';
        if ($action === 'App\\Modules\\CmsCore\\Http\\Controllers\\Web\\CmsMediaServeController@show') {
            $media = $request->route('media');

            return $media instanceof \App\Modules\CmsCore\Models\Media && ! $media->is_internal && $media->is_public !== false;
        }
        if (in_array($action, [FormsWebController::class.'@publicShow', FormsWebController::class.'@publicSubmit'], true)) {
            return FormDefinition::query()->where('slug', $request->route('slug'))->where('target_audience', 'public')->exists();
        }
        if (str_starts_with($action, 'App\\Modules\\CmsCore\\Http\\Controllers\\Web\\CmsPublic')
            || str_starts_with($action, 'App\\Http\\Controllers\\SiteIconController@')
            || str_starts_with($action, 'App\\Modules\\RealEstate\\Http\\Controllers\\Web\\PublicRealEstateWebController@')
            || $action === 'App\\Modules\\CmsCore\\Http\\Controllers\\Web\\CmsEnquiryController@store'
            || $action === 'App\\Modules\\Store\\Http\\Controllers\\Web\\StoreWebController@checkout'
            || $request->routeIs('webhooks.*')) {
            return true;
        }

        $tenant = $this->context->getTenant();
        if ($tenant) {
            foreach ($this->modules->all() as $slug => $manifest) {
                if ($this->modules->isWebsiteExtensionForTenant($slug, $tenant)
                    && $request->routeIs($slug.'.public.*', $slug.'.website.*')) {
                    return true;
                }
            }
        }

        return in_array($request->path(), ['/', 'sample-product', 'sample-product-page', 'product-template', 'product-enterprise', 'login', 'register', 'forgot-password'], true)
            || ($action === 'Closure' && $request->routeIs('cms-core.public.*', 'cms-core.website.*'));
    }

    private function allowsLivewire(Request $request, ?User $user, string $level): bool
    {
        $components = $request->input('components');
        if (! is_array($components) || $components === [] || count($components) > 20) {
            return false;
        }
        try {
            foreach ($components as $component) {
                $snapshot = json_decode($component['snapshot'] ?? '', true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($snapshot) || ! is_string($snapshot['checksum'] ?? null)) {
                    return false;
                }
                Checksum::verify($snapshot);
                $class = mb_ltrim(app(ComponentRegistry::class)->getClass($snapshot['memo']['name'] ?? ''), '\\');
                $tenant = $this->context->getTenant();
                if (! $tenant || ! $this->modules->isEnabledForTenant('core', $tenant)) {
                    return false;
                }
                $calls = ['$refresh'];
                $updates = [];
                if ($class === PricingTable::class) {
                    $calls[] = 'hospitalExample';
                    $updates = ['quoteCurrency', 'selfServiceUsers', 'operationalUsers', 'websitePlan', 'websiteEditors', 'includeSetup', 'fileStorageGiB', 'databaseGiB', 'bandwidthGiB', 'selectedPackages'];
                } elseif ($class === UserProfileSecurity::class && $user !== null) {
                    $model = $snapshot['data']['user'][1] ?? [];
                    if (($model['class'] ?? null) !== $user->getMorphClass() || (string) ($model['key'] ?? '') !== (string) $user->id) {
                        return false;
                    }
                    $calls = [...$calls, 'enableTwoFactor', 'confirmTwoFactor', 'disableTwoFactor'];
                    $updates = ['code', 'password', 'secret', 'showQrCode', 'qrCodeUrl'];
                } elseif ($class === TenantHealthCheck::class && $user !== null && $level === 'operational' && $user->can('core.settings.manage')) {
                    $model = $snapshot['data']['tenant'][1] ?? [];
                    if (($model['class'] ?? null) !== $tenant->getMorphClass() || (string) ($model['key'] ?? '') !== (string) $tenant->id) {
                        return false;
                    }
                    $calls[] = 'runCheck';
                } else {
                    return false;
                }
                foreach ($component['calls'] ?? [] as $call) {
                    if (! in_array($call['method'] ?? null, $calls, true)) {
                        return false;
                    }
                }
                foreach (array_keys($component['updates'] ?? []) as $property) {
                    if (! in_array(explode('.', $property)[0], $updates, true)) {
                        return false;
                    }
                }
            }
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
