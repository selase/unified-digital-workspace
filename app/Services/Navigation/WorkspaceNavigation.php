<?php

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Models\Tenant;
use App\Models\User;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class WorkspaceNavigation
{
    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array{
     *     homeUrl: string,
     *     isTenantContext: bool,
     *     activeModule: array<string, mixed>|null,
     *     sidebar: array{
     *         dashboards: array<int, array<string, mixed>>,
     *         superadmin: array<int, array<string, mixed>>,
     *         tenant: array<int, array<string, mixed>>,
     *         modules: array<int, array<string, mixed>>
     *     },
     *     topMenuGroups: array<int, array<string, mixed>>
     * }
     */
    public function forRequest(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        $tenant = $this->tenantContext->getTenant();
        $tenantSlug = $tenant?->slug;
        $isTenantContext = filled($tenantSlug);
        $currentRouteName = (string) ($request->route()?->getName() ?? '');
        $showSuperadminLinks = (bool) ($user?->can('access-superadmin-dashboard'));

        $dashboards = collect([
            ['label' => 'Admin Dashboard', 'route' => 'dashboard'],
            ['label' => 'Tenant Dashboard', 'route' => 'tenant.dashboard'],
        ])->map(fn (array $item): ?array => $this->resolveLink($item, $tenantSlug, $currentRouteName, $user))->filter()->values();

        $superadmin = collect([
            ['label' => 'Tenants', 'route' => 'tenants.index'],
            ['label' => 'Users', 'route' => 'users.index'],
            ['label' => 'Roles', 'route' => 'roles.index'],
            ['label' => 'Features', 'route' => 'features.index'],
            ['label' => 'Packages', 'route' => 'packages.index'],
            ['label' => 'Leads', 'route' => 'admin.leads.index'],
            ['label' => 'Billing Transactions', 'route' => 'admin.billing.transactions.index'],
            ['label' => 'Billing Subscriptions', 'route' => 'admin.billing.subscriptions.index'],
            ['label' => 'Rate Cards', 'route' => 'admin.billing.rate-cards.index'],
            ['label' => 'Invoices', 'route' => 'admin.billing.invoices.index'],
            ['label' => 'Usage Analytics', 'route' => 'admin.billing.analytics.usage'],
            ['label' => 'Global LLM Usage', 'route' => 'llm-usage.index'],
            ['label' => 'Audit Activity', 'route' => 'audit-trail.activity-logs.index'],
            ['label' => 'Audit Login History', 'route' => 'audit-trail.login-history.index'],
            ['label' => 'Tenant Health', 'route' => 'health.tenants'],
            ['label' => 'Application Health', 'route' => 'application.health'],
            ['label' => 'Developer Tokens', 'route' => 'settings.developer.tokens.index'],
            ['label' => 'My Tenants', 'route' => 'tenant.my-tenants'],
        ])->map(fn (array $item): ?array => $this->resolveLink($item, $tenantSlug, $currentRouteName, $user))->filter()->values();

        $tenantLinks = collect([
            ['label' => 'Organization Settings', 'route' => 'tenant.settings.index'],
            ['label' => 'Billing Settings', 'route' => 'tenant.settings.billing'],
            ['label' => 'Payment Methods', 'route' => 'tenant.settings.payments.index'],
            ['label' => 'Users', 'route' => 'tenant.users.index'],
            ['label' => 'Roles', 'route' => 'tenant.roles.index'],
            ['label' => 'Finance', 'route' => 'tenant.finance.index'],
            ['label' => 'Tenant Billing', 'route' => 'billing.index'],
            ['label' => 'Pricing', 'route' => 'tenant.pricing'],
            ['label' => 'API Keys', 'route' => 'tenant.api-keys.index'],
            ['label' => 'LLM Usage', 'route' => 'tenant.llm-usage.index'],
            ['label' => 'LLM Configuration', 'route' => 'tenant.llm-config.index'],
            ['label' => 'My Tenants', 'route' => 'tenant.my-tenants'],
        ])->map(fn (array $item): ?array => $this->resolveLink($item, $tenantSlug, $currentRouteName, $user))->filter()->values();

        $enabledModuleSlugs = $tenant
            ? $this->moduleManager->getEnabledForTenant($tenant)->pluck('slug')->values()
            : collect();

        $moduleMenus = collect($this->moduleDefinitions())
            ->map(fn (array $moduleConfig, string $slug): ?array => $this->resolveModule($moduleConfig, $slug, $enabledModuleSlugs, $tenantSlug, $currentRouteName, $user, $tenant))
            ->filter()
            ->values();

        $activeModule = $moduleMenus->first(fn (array $module): bool => $module['is_active'] === true);
        $topMenuGroups = $this->resolveTopMenuGroups(
            activeModule: $activeModule,
            dashboards: $dashboards,
            superadmin: $showSuperadminLinks ? $superadmin : collect(),
            tenantLinks: $isTenantContext ? $tenantLinks : collect(),
            moduleMenus: $moduleMenus,
            tenantSlug: $tenantSlug,
            currentRouteName: $currentRouteName,
            user: $user,
        );

        $homeRoute = $isTenantContext ? 'tenant.dashboard' : 'dashboard';
        $homeUrl = $this->routeUrl($homeRoute, $tenantSlug) ?? '#';

        return [
            'homeUrl' => $homeUrl,
            'isTenantContext' => $isTenantContext,
            'activeModule' => $activeModule,
            'sidebar' => [
                'dashboards' => $dashboards->all(),
                'superadmin' => $showSuperadminLinks ? $superadmin->all() : [],
                'tenant' => $isTenantContext ? $tenantLinks->all() : [],
                'modules' => $moduleMenus->all(),
            ],
            'topMenuGroups' => $topMenuGroups->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $moduleConfig
     * @param  Collection<int, string>  $enabledModuleSlugs
     * @return array<string, mixed>|null
     */
    private function resolveModule(
        array $moduleConfig,
        string $slug,
        Collection $enabledModuleSlugs,
        ?string $tenantSlug,
        string $currentRouteName,
        ?User $user,
        ?Tenant $tenant,
    ): ?array {
        if (! $tenant || ! $enabledModuleSlugs->contains($slug)) {
            return null;
        }

        $requiredPermission = $moduleConfig['permission'] ?? null;
        if ($requiredPermission && ! $user?->can($requiredPermission)) {
            return null;
        }

        $homeLink = $this->resolveLink([
            'label' => 'Overview',
            'route' => $moduleConfig['home_route'],
            'active_patterns' => ["{$slug}.*", "api.{$slug}.*"],
        ], $tenantSlug, $currentRouteName, $user);

        $sidebarItems = collect($moduleConfig['sidebar'] ?? [])
            ->map(fn (array $item): ?array => $this->resolveLink($item, $tenantSlug, $currentRouteName, $user))
            ->filter()
            ->values();

        if (! $homeLink && $sidebarItems->isEmpty()) {
            return null;
        }

        $items = collect();
        if ($homeLink) {
            $items->push($homeLink);
        }
        $items = $items->merge($sidebarItems)->values();

        $isActive = Str::startsWith($currentRouteName, "{$slug}.")
            || Str::startsWith($currentRouteName, "api.{$slug}.");

        return [
            'slug' => $slug,
            'label' => $moduleConfig['label'],
            'icon' => $moduleConfig['icon'],
            'is_active' => $isActive,
            'items' => $items->all(),
            'top_menu' => $moduleConfig['top_menu'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $activeModule
     * @param  Collection<int, array<string, mixed>>  $dashboards
     * @param  Collection<int, array<string, mixed>>  $superadmin
     * @param  Collection<int, array<string, mixed>>  $tenantLinks
     * @param  Collection<int, array<string, mixed>>  $moduleMenus
     * @return Collection<int, array<string, mixed>>
     */
    private function resolveTopMenuGroups(
        ?array $activeModule,
        Collection $dashboards,
        Collection $superadmin,
        Collection $tenantLinks,
        Collection $moduleMenus,
        ?string $tenantSlug,
        string $currentRouteName,
        ?User $user,
    ): Collection {
        if ($activeModule) {
            return collect($activeModule['top_menu'])
                ->map(function (array $group) use ($tenantSlug, $currentRouteName, $user): ?array {
                    $items = collect($group['items'] ?? [])
                        ->map(fn (array $item): ?array => $this->resolveLink($item, $tenantSlug, $currentRouteName, $user))
                        ->filter()
                        ->values();

                    if ($items->isEmpty()) {
                        return null;
                    }

                    return [
                        'label' => $group['label'],
                        'items' => $items->all(),
                    ];
                })
                ->filter()
                ->values();
        }

        $groups = collect([
            [
                'label' => 'Workspace',
                'items' => $dashboards->all(),
            ],
            [
                'label' => 'Administration',
                'items' => $superadmin->take(8)->all(),
            ],
            [
                'label' => 'Tenant',
                'items' => $tenantLinks->take(8)->all(),
            ],
            [
                'label' => 'Modules',
                'items' => $moduleMenus->map(function (array $module): array {
                    return [
                        'label' => $module['label'],
                        'url' => $module['items'][0]['url'] ?? '#',
                        'is_active' => $module['is_active'],
                    ];
                })->all(),
            ],
        ])->filter(fn (array $group): bool => ! empty($group['items']))->values();

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function resolveLink(array $item, ?string $tenantSlug, string $currentRouteName, ?User $user): ?array
    {
        $permission = $item['permission'] ?? null;
        if ($permission && ! $user?->can($permission)) {
            return null;
        }

        $url = $item['url'] ?? null;
        if (! $url && isset($item['route'])) {
            $url = $this->routeUrl(
                routeName: (string) $item['route'],
                tenantSlug: $tenantSlug,
                params: (array) ($item['params'] ?? []),
            );
        }

        if (! $url) {
            return null;
        }

        $patterns = (array) ($item['active_patterns'] ?? []);
        if (isset($item['route'])) {
            $patterns[] = (string) $item['route'];
        }

        $isActive = collect($patterns)
            ->filter(fn (string $pattern): bool => $pattern !== '')
            ->contains(fn (string $pattern): bool => Str::is($pattern, $currentRouteName));

        return [
            'label' => (string) $item['label'],
            'url' => $url,
            'is_active' => $isActive,
        ];
    }

    private function routeUrl(string $routeName, ?string $tenantSlug, array $params = []): ?string
    {
        if (! Route::has($routeName)) {
            return null;
        }

        if ($tenantSlug) {
            $params['subdomain'] ??= $tenantSlug;
        }

        try {
            return route($routeName, $params);
        } catch (UrlGenerationException) {
            return null;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function moduleDefinitions(): array
    {
        return [
            'document-management' => [
                'label' => 'Document Management',
                'icon' => 'ki-filled ki-document',
                'permission' => 'documents.view',
                'home_route' => 'document-management.index',
                'sidebar' => [
                    ['label' => 'Document Library', 'route' => 'document-management.documents.index'],
                    ['label' => 'Quiz Library', 'route' => 'document-management.quizzes.index'],
                    ['label' => 'Quiz Analytics', 'route' => 'document-management.analytics.index', 'permission' => 'documents.manage_quizzes'],
                    ['label' => 'Audit Timeline', 'route' => 'document-management.audits.index', 'permission' => 'documents.audit.view'],
                    ['label' => 'Documents API', 'route' => 'api.document-management.v1.documents.index'],
                    ['label' => 'Module API Root', 'route' => 'api.document-management.index'],
                ],
                'top_menu' => [
                    [
                        'label' => 'Overview',
                        'items' => [
                            ['label' => 'Document Hub', 'route' => 'document-management.index'],
                            ['label' => 'Document Library', 'route' => 'document-management.documents.index'],
                            ['label' => 'Quiz Library', 'route' => 'document-management.quizzes.index'],
                            ['label' => 'Quiz Analytics', 'route' => 'document-management.analytics.index', 'permission' => 'documents.manage_quizzes'],
                        ],
                    ],
                    [
                        'label' => 'Compliance',
                        'items' => [
                            ['label' => 'Audit Timeline', 'route' => 'document-management.audits.index', 'permission' => 'documents.audit.view'],
                            ['label' => 'Documents API', 'route' => 'api.document-management.v1.documents.index'],
                            ['label' => 'Document Details API', 'route' => 'api.document-management.v1.documents.show', 'params' => ['document' => 1]],
                            ['label' => 'Quiz API', 'route' => 'api.document-management.v1.documents.quizzes.show', 'params' => ['document' => 1, 'quiz' => 1]],
                        ],
                    ],
                ],
            ],
            'memos' => [
                'label' => 'Memos',
                'icon' => 'ki-filled ki-note-2',
                'permission' => 'memos.view',
                'home_route' => 'memos.index',
                'sidebar' => [
                    ['label' => 'Memo API', 'route' => 'api.memos.v1.memos.index'],
                    ['label' => 'Module API Root', 'route' => 'api.memos.index'],
                ],
                'top_menu' => [
                    [
                        'label' => 'Overview',
                        'items' => [
                            ['label' => 'Memos Hub', 'route' => 'memos.index'],
                            ['label' => 'Memo API List', 'route' => 'api.memos.v1.memos.index'],
                        ],
                    ],
                    [
                        'label' => 'Workflow',
                        'items' => [
                            ['label' => 'Memo Details API', 'route' => 'api.memos.v1.memos.show', 'params' => ['memo' => 1]],
                            ['label' => 'Module API Root', 'route' => 'api.memos.index'],
                        ],
                    ],
                ],
            ],
            'forums' => [
                'label' => 'Forums & Messaging',
                'icon' => 'ki-filled ki-message-text-2',
                'permission' => 'forums.view',
                'home_route' => 'forums.hub',
                'sidebar' => [
                    ['label' => 'Channel Directory', 'route' => 'forums.channels.index'],
                    ['label' => 'Thread Queue', 'route' => 'forums.threads.index'],
                    ['label' => 'Message Center', 'route' => 'forums.messages.index'],
                    ['label' => 'Moderation Queue', 'route' => 'forums.moderation.index', 'permission' => 'forums.moderate'],
                    ['label' => 'Channels API', 'route' => 'api.forums.v1.channels.index'],
                    ['label' => 'Threads API', 'route' => 'api.forums.v1.threads.index'],
                    ['label' => 'Messages API', 'route' => 'api.forums.v1.messages.index'],
                ],
                'top_menu' => [
                    [
                        'label' => 'Overview',
                        'items' => [
                            ['label' => 'Forums Hub', 'route' => 'forums.hub'],
                            ['label' => 'Channels', 'route' => 'forums.channels.index'],
                            ['label' => 'Threads', 'route' => 'forums.threads.index'],
                            ['label' => 'Messages', 'route' => 'forums.messages.index'],
                            ['label' => 'Module API Root', 'route' => 'api.forums.index'],
                        ],
                    ],
                    [
                        'label' => 'Discussions',
                        'items' => [
                            ['label' => 'Moderation Queue', 'route' => 'forums.moderation.index', 'permission' => 'forums.moderate'],
                            ['label' => 'Channels API', 'route' => 'api.forums.v1.channels.index'],
                            ['label' => 'Threads API', 'route' => 'api.forums.v1.threads.index'],
                            ['label' => 'Messages API', 'route' => 'api.forums.v1.messages.index'],
                            ['label' => 'Moderation', 'route' => 'api.forums.v1.moderation.overview', 'permission' => 'forums.moderate'],
                        ],
                    ],
                ],
            ],
            'incident-management' => [
                'label' => 'Incident Management',
                'icon' => 'ki-filled ki-shield-cross',
                'permission' => 'incidents.view',
                'home_route' => 'incident-management.index',
                'sidebar' => [
                    ['label' => 'Incident Register', 'route' => 'incident-management.incidents.index'],
                    ['label' => 'Task Board', 'route' => 'incident-management.tasks.index'],
                    ['label' => 'Progress Reports', 'route' => 'incident-management.reports.index'],
                    ['label' => 'Incidents API', 'route' => 'api.incident-management.v1.incidents.index'],
                    ['label' => 'Incidents Stats API', 'route' => 'api.incident-management.v1.incidents.stats'],
                    ['label' => 'Module API Root', 'route' => 'api.incident-management.index'],
                ],
                'top_menu' => [
                    [
                        'label' => 'Overview',
                        'items' => [
                            ['label' => 'Incident Hub', 'route' => 'incident-management.index'],
                            ['label' => 'Incident Register', 'route' => 'incident-management.incidents.index'],
                            ['label' => 'Task Board', 'route' => 'incident-management.tasks.index'],
                            ['label' => 'Progress Reports', 'route' => 'incident-management.reports.index'],
                            ['label' => 'Incidents API', 'route' => 'api.incident-management.v1.incidents.index'],
                        ],
                    ],
                    [
                        'label' => 'Operations',
                        'items' => [
                            ['label' => 'Incident Stats', 'route' => 'api.incident-management.v1.incidents.stats'],
                            ['label' => 'Incidents Export', 'route' => 'api.incident-management.v1.incidents.export'],
                            ['label' => 'Incident Details API', 'route' => 'api.incident-management.v1.incidents.show', 'params' => ['incident' => 1]],
                        ],
                    ],
                ],
            ],
            'hrms-core' => [
                'label' => 'HRMS',
                'icon' => 'ki-filled ki-people',
                'permission' => 'hrms.employees.view',
                'home_route' => 'hrms-core.index',
                'sidebar' => [
                    ['label' => 'Employee Directory', 'route' => 'hrms-core.employees.index', 'permission' => 'hrms.employees.view'],
                    ['label' => 'Departments', 'route' => 'hrms-core.departments.index', 'permission' => 'hrms.departments.view'],
                    ['label' => 'Leave Requests', 'route' => 'hrms-core.leave-requests.index', 'permission' => 'hrms.leave.view'],
                    ['label' => 'Recruitment', 'route' => 'hrms-core.recruitment.index', 'permission' => 'hrms.jobs.view'],
                    ['label' => 'Employees API', 'route' => 'api.hrms-core.v1.employees.index'],
                    ['label' => 'Leave API', 'route' => 'api.hrms-core.v1.leave-requests.index'],
                    ['label' => 'Recruitment API', 'route' => 'api.hrms-core.v1.job-postings.index'],
                ],
                'top_menu' => [
                    [
                        'label' => 'Core',
                        'items' => [
                            ['label' => 'HRMS Hub', 'route' => 'hrms-core.index'],
                            ['label' => 'Employees', 'route' => 'hrms-core.employees.index', 'permission' => 'hrms.employees.view'],
                            ['label' => 'Departments', 'route' => 'hrms-core.departments.index', 'permission' => 'hrms.departments.view'],
                        ],
                    ],
                    [
                        'label' => 'People Ops',
                        'items' => [
                            ['label' => 'Leave Requests', 'route' => 'hrms-core.leave-requests.index', 'permission' => 'hrms.leave.view'],
                            ['label' => 'Recruitment', 'route' => 'hrms-core.recruitment.index', 'permission' => 'hrms.jobs.view'],
                            ['label' => 'Appraisals API', 'route' => 'api.hrms-core.v1.appraisals.index'],
                            ['label' => 'Promotions API', 'route' => 'api.hrms-core.v1.promotions.index'],
                        ],
                    ],
                ],
            ],
            'cms-core' => [
                'label' => 'CMS',
                'icon' => 'ki-filled ki-book-open',
                'permission' => 'cms.posts.view',
                'home_route' => 'cms-core.index',
                'sidebar' => [
                    ['label' => 'Posts', 'route' => 'cms-core.posts.index', 'permission' => 'cms.posts.view'],
                    ['label' => 'Media Library', 'route' => 'cms-core.media.index', 'permission' => 'cms.media.view'],
                    ['label' => 'Menus', 'route' => 'cms-core.menus.index', 'permission' => 'cms.menus.view'],
                    ['label' => 'Posts API', 'route' => 'api.cms-core.v1.posts.index'],
                    ['label' => 'Taxonomy API', 'route' => 'api.cms-core.v1.categories.index'],
                    ['label' => 'Media API', 'route' => 'api.cms-core.v1.media.index'],
                ],
                'top_menu' => [
                    [
                        'label' => 'Publishing',
                        'items' => [
                            ['label' => 'CMS Hub', 'route' => 'cms-core.index'],
                            ['label' => 'Posts', 'route' => 'cms-core.posts.index', 'permission' => 'cms.posts.view'],
                            ['label' => 'Menus', 'route' => 'cms-core.menus.index', 'permission' => 'cms.menus.view'],
                            ['label' => 'Post Types', 'route' => 'api.cms-core.v1.post-types.index'],
                        ],
                    ],
                    [
                        'label' => 'Taxonomy & Assets',
                        'items' => [
                            ['label' => 'Categories', 'route' => 'api.cms-core.v1.categories.index'],
                            ['label' => 'Tags', 'route' => 'api.cms-core.v1.tags.index'],
                            ['label' => 'Media', 'route' => 'cms-core.media.index', 'permission' => 'cms.media.view'],
                        ],
                    ],
                ],
            ],
            'project-management' => [
                'label' => 'Project Management',
                'icon' => 'ki-filled ki-kanban',
                'permission' => 'projects.view',
                'home_route' => 'project-management.index',
                'sidebar' => [
                    ['label' => 'Projects API', 'route' => 'api.project-management.v1.projects.index'],
                    ['label' => 'Project Summary API', 'route' => 'api.project-management.v1.projects.summary', 'params' => ['project' => 1]],
                    ['label' => 'Project Gantt API', 'route' => 'api.project-management.v1.projects.gantt', 'params' => ['project' => 1]],
                ],
                'top_menu' => [
                    [
                        'label' => 'Core',
                        'items' => [
                            ['label' => 'Project Hub', 'route' => 'project-management.index'],
                            ['label' => 'Projects', 'route' => 'api.project-management.v1.projects.index'],
                            ['label' => 'Tasks', 'route' => 'api.project-management.v1.projects.tasks.index', 'params' => ['project' => 1]],
                        ],
                    ],
                    [
                        'label' => 'Planning',
                        'items' => [
                            ['label' => 'Summary', 'route' => 'api.project-management.v1.projects.summary', 'params' => ['project' => 1]],
                            ['label' => 'Gantt', 'route' => 'api.project-management.v1.projects.gantt', 'params' => ['project' => 1]],
                        ],
                    ],
                ],
            ],
            'quality-monitoring' => [
                'label' => 'Quality Monitoring',
                'icon' => 'ki-filled ki-chart-line',
                'permission' => 'qm.workplans.view',
                'home_route' => 'quality-monitoring.index',
                'sidebar' => [
                    ['label' => 'Workplans API', 'route' => 'api.quality-monitoring.v1.workplans.index'],
                    ['label' => 'Alerts API', 'route' => 'api.quality-monitoring.v1.alerts.index'],
                    ['label' => 'Indicators API', 'route' => 'api.quality-monitoring.v1.indicators.index'],
                ],
                'top_menu' => [
                    [
                        'label' => 'Quality Ops',
                        'items' => [
                            ['label' => 'Quality Hub', 'route' => 'quality-monitoring.index'],
                            ['label' => 'Workplans', 'route' => 'api.quality-monitoring.v1.workplans.index'],
                            ['label' => 'Dashboard', 'route' => 'api.quality-monitoring.v1.workplans.dashboard', 'params' => ['workplan' => 1]],
                        ],
                    ],
                    [
                        'label' => 'Reports',
                        'items' => [
                            ['label' => 'Summary', 'route' => 'api.quality-monitoring.v1.workplans.reports.summary', 'params' => ['workplan' => 1]],
                            ['label' => 'Variances', 'route' => 'api.quality-monitoring.v1.workplans.reports.variances', 'params' => ['workplan' => 1]],
                            ['label' => 'Alerts', 'route' => 'api.quality-monitoring.v1.alerts.index'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
