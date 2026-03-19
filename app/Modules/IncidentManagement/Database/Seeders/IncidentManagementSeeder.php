<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Database\Seeders;

use App\Models\User;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentCategory;
use App\Modules\IncidentManagement\Models\IncidentComment;
use App\Modules\IncidentManagement\Models\IncidentPriority;
use App\Modules\IncidentManagement\Models\IncidentProgressReport;
use App\Modules\IncidentManagement\Models\IncidentSla;
use App\Modules\IncidentManagement\Models\IncidentStatus;
use App\Modules\IncidentManagement\Models\IncidentTask;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

final class IncidentManagementSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->bound(TenantContext::class)) {
            return;
        }

        $tenant = app(TenantContext::class)->getTenant();

        if (! $tenant) {
            return;
        }

        $tenantConnection = config('database.default_tenant_connection', 'tenant');

        if (! Schema::connection($tenantConnection)->hasTable('incidents')) {
            return;
        }

        $owner = User::query()
            ->where('tenant_id', $tenant->id)
            ->first() ?? User::query()->first();

        if (! $owner) {
            return;
        }

        $openStatus = IncidentStatus::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'open',
        ], [
            'name' => 'Open',
            'sort_order' => 1,
            'is_default' => true,
            'is_terminal' => false,
        ]);

        $inProgressStatus = IncidentStatus::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'in-progress',
        ], [
            'name' => 'In Progress',
            'sort_order' => 2,
            'is_default' => false,
            'is_terminal' => false,
        ]);

        $resolvedStatus = IncidentStatus::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'resolved',
        ], [
            'name' => 'Resolved',
            'sort_order' => 3,
            'is_default' => false,
            'is_terminal' => true,
        ]);

        $highPriority = IncidentPriority::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'high',
        ], [
            'name' => 'High',
            'level' => 3,
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 480,
            'is_active' => true,
        ]);

        $mediumPriority = IncidentPriority::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'medium',
        ], [
            'name' => 'Medium',
            'level' => 2,
            'response_time_minutes' => 180,
            'resolution_time_minutes' => 1_440,
            'is_active' => true,
        ]);

        $securityCategory = IncidentCategory::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'security',
        ], [
            'name' => 'Security',
            'description' => 'Security incidents and suspicious activity.',
            'is_active' => true,
        ]);

        $serviceCategory = IncidentCategory::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'service-outage',
        ], [
            'name' => 'Service Outage',
            'description' => 'System availability and platform outage incidents.',
            'is_active' => true,
        ]);

        $loginIncident = Incident::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'reference_code' => 'INC2026-12001',
        ], [
            'title' => 'Login Portal Delay',
            'description' => 'Users report delayed sign-in response during peak hours.',
            'category_id' => $serviceCategory->id,
            'priority_id' => $mediumPriority->id,
            'status_id' => $inProgressStatus->id,
            'reported_by_id' => (string) $owner->uuid,
            'assigned_to_id' => (string) $owner->uuid,
            'reported_via' => 'internal',
            'source' => 'web',
            'due_at' => now()->addHours(8),
            'metadata' => [
                'seed' => true,
                'service' => 'auth',
            ],
            'impact' => 'moderate',
        ]);

        $securityIncident = Incident::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'reference_code' => 'INC2026-12002',
        ], [
            'title' => 'Suspicious Password Reset Attempts',
            'description' => 'Multiple failed reset attempts detected on two accounts.',
            'category_id' => $securityCategory->id,
            'priority_id' => $highPriority->id,
            'status_id' => $openStatus->id,
            'reported_by_id' => (string) $owner->uuid,
            'assigned_to_id' => (string) $owner->uuid,
            'reported_via' => 'internal',
            'source' => 'security-monitor',
            'due_at' => now()->addHours(2),
            'metadata' => [
                'seed' => true,
                'alerts' => 4,
            ],
            'impact' => 'high',
        ]);

        $resolvedIncident = Incident::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'reference_code' => 'INC2026-12003',
        ], [
            'title' => 'API Timeout on Billing Endpoint',
            'description' => 'Timeout spikes traced to stale query plan in billing service.',
            'category_id' => $serviceCategory->id,
            'priority_id' => $mediumPriority->id,
            'status_id' => $resolvedStatus->id,
            'reported_by_id' => (string) $owner->uuid,
            'assigned_to_id' => (string) $owner->uuid,
            'reported_via' => 'internal',
            'source' => 'api-gateway',
            'due_at' => now()->subHours(12),
            'resolved_at' => now()->subHours(4),
            'metadata' => [
                'seed' => true,
                'patch' => 'BILL-214',
            ],
            'impact' => 'medium',
        ]);

        IncidentTask::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'incident_id' => $loginIncident->id,
            'title' => 'Collect auth latency metrics',
        ], [
            'assigned_to_id' => (string) $owner->uuid,
            'description' => 'Capture p95/p99 sign-in timings for the last 24 hours.',
            'status' => 'ongoing',
            'due_at' => now()->addHours(4),
            'sort_order' => 1,
        ]);

        IncidentTask::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'incident_id' => $securityIncident->id,
            'title' => 'Rotate impacted credentials',
        ], [
            'assigned_to_id' => (string) $owner->uuid,
            'description' => 'Force reset and revoke active sessions for impacted users.',
            'status' => 'ongoing',
            'due_at' => now()->addHour(),
            'sort_order' => 1,
        ]);

        IncidentComment::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'incident_id' => $loginIncident->id,
            'body' => 'Escalated to platform team for cache review.',
        ], [
            'user_id' => (string) $owner->uuid,
            'is_internal' => true,
        ]);

        IncidentComment::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'incident_id' => $securityIncident->id,
            'body' => 'Security team investigating suspicious IP addresses.',
        ], [
            'user_id' => (string) $owner->uuid,
            'is_internal' => true,
        ]);

        IncidentProgressReport::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'incident_id' => $resolvedIncident->id,
            'body' => 'Root cause isolated and deployed to production.',
        ], [
            'user_id' => (string) $owner->uuid,
            'is_internal' => true,
        ]);

        IncidentSla::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'incident_id' => $securityIncident->id,
        ], [
            'response_due_at' => now()->addMinutes(45),
            'resolution_due_at' => now()->addHours(6),
            'first_response_at' => now()->subMinutes(10),
            'is_breached' => false,
        ]);
    }
}
