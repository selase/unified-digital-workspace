<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\ModuleManager;
use App\Services\Tenancy\FeatureMeteringService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

final class DashboardController extends Controller
{
    public function index(ModuleManager $moduleManager, FeatureMeteringService $meteringService): Factory|View
    {
        $tenant = app(TenantContext::class)->getTenant();
        abort_if(! $tenant, 404, 'Tenant context not found.');

        // 1. Fetch usage for metered features
        $usages = [];
        foreach ($tenant->features()->where('enabled', true)->get() as $feature) {
            if (isset($feature->meta['type']) && $feature->meta['type'] === 'limit') {
                $usages[] = $meteringService->getUsage($tenant, $feature->feature_key);
            }
        }

        // 2. Checklist Logic
        $checklist = [
            'onboarding' => (bool) $tenant->onboarding_completed_at,
            'team' => $tenant->users()->count() > 1,
            'branding' => (! empty($tenant->logo) || ! empty(data_get($tenant->meta, 'branding.primary_color')) || ! empty(data_get($tenant->meta, 'primary_color'))),
        ];

        // 3. Multi-Module Operational Telemetry
        $moduleCards = [];
        $enabledModules = $moduleManager->getEnabledForTenant($tenant);

        // HRMS telemetry
        if ($moduleManager->isEnabledForTenant('hrms-core', $tenant) && class_exists(\App\Modules\HrmsCore\Models\Employees\Employee::class)) {
            $headcount = \App\Modules\HrmsCore\Models\Employees\Employee::count();
            $activeStaff = \App\Modules\HrmsCore\Models\Employees\Employee::where('is_active', true)->count();
            $pendingLeaves = class_exists(\App\Modules\HrmsCore\Models\Leave\LeaveRequest::class)
                ? \App\Modules\HrmsCore\Models\Leave\LeaveRequest::where('status', 'pending')->count()
                : 0;

            $moduleCards['hrms'] = [
                'name' => 'HRMS & Staff',
                'route' => Route::has('hrms.index') ? route('hrms.index') : '#',
                'icon' => 'ki-people',
                'badge' => 'Active',
                'kpis' => [
                    ['label' => 'Total Headcount', 'value' => $headcount],
                    ['label' => 'Active Staff', 'value' => $activeStaff],
                    ['label' => 'Pending Leaves', 'value' => $pendingLeaves],
                ],
            ];
        }

        // Memos telemetry
        if ($moduleManager->isEnabledForTenant('memos', $tenant) && class_exists(\App\Modules\Memos\Models\Memo::class)) {
            $totalMemos = \App\Modules\Memos\Models\Memo::count();
            $circulation = \App\Modules\Memos\Models\Memo::whereIn('status', ['draft', 'pending_verification'])->count();
            $dispatched = \App\Modules\Memos\Models\Memo::whereIn('status', ['sent', 'acknowledged'])->count();

            $moduleCards['memos'] = [
                'name' => 'Memos e-Office',
                'route' => Route::has('memos.index') ? route('memos.index') : '#',
                'icon' => 'ki-document',
                'badge' => 'Active',
                'kpis' => [
                    ['label' => 'Total Memos', 'value' => $totalMemos],
                    ['label' => 'In Draft', 'value' => $circulation],
                    ['label' => 'Dispatched', 'value' => $dispatched],
                ],
            ];
        }

        // Document Management telemetry
        if ($moduleManager->isEnabledForTenant('document-management', $tenant) && class_exists(\App\Modules\DocumentManagement\Models\Document::class)) {
            $totalDocs = \App\Modules\DocumentManagement\Models\Document::count();
            $publishedDocs = \App\Modules\DocumentManagement\Models\Document::where('status', 'published')->count();
            $draftDocs = \App\Modules\DocumentManagement\Models\Document::where('status', 'draft')->count();

            $moduleCards['documents'] = [
                'name' => 'Controlled Documents',
                'route' => Route::has('documents.index') ? route('documents.index') : '#',
                'icon' => 'ki-folder',
                'badge' => 'ISO Vault',
                'kpis' => [
                    ['label' => 'Controlled Docs', 'value' => $totalDocs],
                    ['label' => 'Published', 'value' => $publishedDocs],
                    ['label' => 'Drafts', 'value' => $draftDocs],
                ],
            ];
        }

        // Incident Management telemetry
        if ($moduleManager->isEnabledForTenant('incident-management', $tenant) && class_exists(\App\Modules\IncidentManagement\Models\Incident::class)) {
            $totalIncidents = \App\Modules\IncidentManagement\Models\Incident::count();
            $openIncidents = \App\Modules\IncidentManagement\Models\Incident::whereNull('resolved_at')->whereNull('closed_at')->count();
            $capaTasks = class_exists(\App\Modules\IncidentManagement\Models\IncidentTask::class)
                ? \App\Modules\IncidentManagement\Models\IncidentTask::whereNull('completed_at')->count()
                : 0;

            $moduleCards['incidents'] = [
                'name' => 'Incident & CAPA',
                'route' => Route::has('incidents.index') ? route('incidents.index') : '#',
                'icon' => 'ki-shield-cross',
                'badge' => 'Safety',
                'kpis' => [
                    ['label' => 'Occurrences', 'value' => $totalIncidents],
                    ['label' => 'Open Reports', 'value' => $openIncidents],
                    ['label' => 'Pending CAPAs', 'value' => $capaTasks],
                ],
            ];
        }

        // Quality Monitoring telemetry
        if ($moduleManager->isEnabledForTenant('quality-monitoring', $tenant) && class_exists(\App\Modules\QualityMonitoring\Models\Workplan::class)) {
            $workplans = \App\Modules\QualityMonitoring\Models\Workplan::where('status', 'active')->count();
            $kpis = class_exists(\App\Modules\QualityMonitoring\Models\Kpi::class)
                ? \App\Modules\QualityMonitoring\Models\Kpi::count()
                : 0;
            $alerts = class_exists(\App\Modules\QualityMonitoring\Models\Alert::class)
                ? \App\Modules\QualityMonitoring\Models\Alert::where('status', 'open')->count()
                : 0;

            $moduleCards['quality'] = [
                'name' => 'Quality Monitoring',
                'route' => Route::has('quality-monitoring.index') ? route('quality-monitoring.index') : '#',
                'icon' => 'ki-chart-line-star',
                'badge' => 'JCI Engine',
                'kpis' => [
                    ['label' => 'Active Workplans', 'value' => $workplans],
                    ['label' => 'Monitored KPIs', 'value' => $kpis],
                    ['label' => 'Open Alerts', 'value' => $alerts],
                ],
            ];
        }

        // Project Management telemetry
        if ($moduleManager->isEnabledForTenant('project-management', $tenant) && class_exists(\App\Modules\ProjectManagement\Models\Project::class)) {
            $projects = \App\Modules\ProjectManagement\Models\Project::count();
            $activeProjects = \App\Modules\ProjectManagement\Models\Project::whereIn('status', ['planned', 'in-progress'])->count();
            $overdueTasks = class_exists(\App\Modules\ProjectManagement\Models\Task::class)
                ? \App\Modules\ProjectManagement\Models\Task::where('status', '!=', 'done')->where('due_date', '<', now())->count()
                : 0;

            $moduleCards['projects'] = [
                'name' => 'Project Governance',
                'route' => Route::has('projects.index') ? route('projects.index') : '#',
                'icon' => 'ki-element-11',
                'badge' => 'Stage-Gate',
                'kpis' => [
                    ['label' => 'Charters', 'value' => $projects],
                    ['label' => 'Active Pipeline', 'value' => $activeProjects],
                    ['label' => 'Overdue Tasks', 'value' => $overdueTasks],
                ],
            ];
        }

        // Forums telemetry
        if ($moduleManager->isEnabledForTenant('forums', $tenant) && class_exists(\App\Modules\Forums\Models\ForumChannel::class)) {
            $channels = \App\Modules\Forums\Models\ForumChannel::count();
            $threads = class_exists(\App\Modules\Forums\Models\ForumThread::class)
                ? \App\Modules\Forums\Models\ForumThread::count()
                : 0;
            $flagged = class_exists(\App\Modules\Forums\Models\ForumThread::class)
                ? \App\Modules\Forums\Models\ForumThread::where('status', 'flagged')->count()
                : 0;

            $moduleCards['forums'] = [
                'name' => 'Forums & Collab',
                'route' => Route::has('forums.hub') ? route('forums.hub') : '#',
                'icon' => 'ki-messages',
                'badge' => 'Workspaces',
                'kpis' => [
                    ['label' => 'Channels', 'value' => $channels],
                    ['label' => 'Discussions', 'value' => $threads],
                    ['label' => 'Moderation', 'value' => $flagged],
                ],
            ];
        }

        // CMS Core telemetry
        if ($moduleManager->isEnabledForTenant('cms-core', $tenant) && class_exists(\App\Modules\CmsCore\Models\Post::class)) {
            $posts = \App\Modules\CmsCore\Models\Post::count();
            $publishedPosts = \App\Modules\CmsCore\Models\Post::where('status', 'published')->count();
            $draftPosts = \App\Modules\CmsCore\Models\Post::where('status', 'draft')->count();

            $moduleCards['cms'] = [
                'name' => 'CMS & Portals',
                'route' => Route::has('cms-core.index') ? route('cms-core.index') : '#',
                'icon' => 'ki-book-open',
                'badge' => 'Live Portals',
                'kpis' => [
                    ['label' => 'Total Entries', 'value' => $posts],
                    ['label' => 'Published Live', 'value' => $publishedPosts],
                    ['label' => 'Drafts Queue', 'value' => $draftPosts],
                ],
            ];
        }

        // 4. Executive Summary KPI Totals (aggregates for top KPI ribbon)
        $executiveKpis = [
            'staff_count' => isset($moduleCards['hrms']) ? $moduleCards['hrms']['kpis'][0]['value'] : $tenant->users()->count(),
            'pending_governance' => (isset($moduleCards['memos']) ? $moduleCards['memos']['kpis'][1]['value'] : 0)
                + (isset($moduleCards['incidents']) ? $moduleCards['incidents']['kpis'][1]['value'] : 0)
                + (isset($moduleCards['quality']) ? $moduleCards['quality']['kpis'][2]['value'] : 0),
            'controlled_assets' => (isset($moduleCards['documents']) ? $moduleCards['documents']['kpis'][0]['value'] : 0)
                + (isset($moduleCards['cms']) ? $moduleCards['cms']['kpis'][0]['value'] : 0),
            'active_operations' => (isset($moduleCards['projects']) ? $moduleCards['projects']['kpis'][1]['value'] : 0)
                + (isset($moduleCards['quality']) ? $moduleCards['quality']['kpis'][0]['value'] : 0),
            'enabled_modules_count' => $enabledModules->count(),
        ];

        // 5. Recent Executive Action Items
        /** @var array<int, array{module: string, title: string, reference: string, status: string, badge: string, url: string, timestamp: string}> $actionItems */
        $actionItems = [];

        if (isset($moduleCards['incidents']) && class_exists(\App\Modules\IncidentManagement\Models\Incident::class)) {
            $unresolvedIncidents = \App\Modules\IncidentManagement\Models\Incident::whereNull('resolved_at')
                ->whereNull('closed_at')
                ->latest()
                ->limit(2)
                ->get();
            foreach ($unresolvedIncidents as $inc) {
                $actionItems[] = [
                    'module' => 'Incident',
                    'title' => (string) $inc->title,
                    'reference' => (string) ($inc->reference_code ?? ('INC-'.$inc->id)),
                    'status' => 'open',
                    'badge' => 'danger',
                    'url' => Route::has('incidents.show') ? route('incidents.show', $inc) : '#',
                    'timestamp' => $inc->created_at?->diffForHumans() ?? 'Recent',
                ];
            }
        }

        if (isset($moduleCards['quality']) && class_exists(\App\Modules\QualityMonitoring\Models\Alert::class)) {
            $openAlerts = \App\Modules\QualityMonitoring\Models\Alert::where('status', 'open')
                ->latest()
                ->limit(2)
                ->get();
            foreach ($openAlerts as $alert) {
                $alertMessage = ucfirst((string) $alert->type);
                $actionItems[] = [
                    'module' => 'Quality Alert',
                    'title' => $alertMessage,
                    'reference' => 'ALERT-'.$alert->id,
                    'status' => (string) ($alert->escalation_level ?? $alert->status),
                    'badge' => 'warning',
                    'url' => Route::has('quality-monitoring.alerts.index') ? route('quality-monitoring.alerts.index') : '#',
                    'timestamp' => $alert->created_at?->diffForHumans() ?? 'Recent',
                ];
            }
        }

        if (isset($moduleCards['memos']) && class_exists(\App\Modules\Memos\Models\Memo::class)) {
            $pendingMemos = \App\Modules\Memos\Models\Memo::whereIn('status', ['draft', 'pending_verification'])
                ->latest()
                ->limit(2)
                ->get();
            foreach ($pendingMemos as $memo) {
                $actionItems[] = [
                    'module' => 'Memo',
                    'title' => (string) $memo->subject,
                    'reference' => 'MEMO-'.$memo->id,
                    'status' => (string) $memo->status,
                    'badge' => 'primary',
                    'url' => Route::has('memos.show') ? route('memos.show', $memo) : '#',
                    'timestamp' => $memo->created_at?->diffForHumans() ?? 'Recent',
                ];
            }
        }

        return view('tenant.dashboard', compact(
            'tenant',
            'usages',
            'checklist',
            'moduleCards',
            'executiveKpis',
            'actionItems'
        ));
    }
}
