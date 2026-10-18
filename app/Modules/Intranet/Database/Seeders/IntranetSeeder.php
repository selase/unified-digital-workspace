<?php

declare(strict_types=1);

namespace App\Modules\Intranet\Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\Intranet\Models\IntranetBoardPost;
use App\Modules\Intranet\Models\IntranetEvent;
use App\Modules\Intranet\Models\IntranetEventRsvp;
use App\Modules\Intranet\Models\IntranetMessageBoard;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

final class IntranetSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->bound(TenantContext::class)) {
            return;
        }

        $tenant = app(TenantContext::class)->getTenant();

        if (! $tenant instanceof Tenant) {
            $tenant = Tenant::query()->where('slug', 'purpledot')->first()
                ?? Tenant::query()->where('isolation_mode', 'shared')->first();
        }

        if (! $tenant instanceof Tenant) {
            return;
        }

        $tenantConnection = config('database.default_tenant_connection', 'tenant');

        if (! Schema::connection($tenantConnection)->hasTable('intranet_message_boards')) {
            return;
        }

        $author = User::query()
            ->where('tenant_id', $tenant->id)
            ->first() ?? User::query()->first();

        if (! $author) {
            return;
        }

        // 1. Department resolution (if HRMS departments table exists)
        $clinicalDeptId = null;
        if (Schema::connection($tenantConnection)->hasTable('hrms_departments')) {
            $dept = Department::query()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'slug' => 'clinical-services',
            ], [
                'name' => 'Clinical Services & Nursing',
                'description' => 'Clinical practice, emergency medicine and patient care teams.',
                'is_active' => true,
            ]);
            $clinicalDeptId = $dept->id;
        }

        // 2. Seed Message Boards
        $boardInternal = IntranetMessageBoard::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'institutional-executive-noticeboard',
        ], [
            'title' => 'Institutional Executive Noticeboard',
            'description' => 'Official leadership announcements, compliance directives, and institution-wide notices.',
            'audience' => IntranetMessageBoard::AUDIENCE_INTERNAL,
            'department_id' => null,
            'is_pinned' => true,
        ]);

        IntranetMessageBoard::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'clinical-operations-board',
        ], [
            'title' => 'Clinical Operations & CME Discussion',
            'description' => 'Departmental discussion board for doctors, nurses and clinical operations specialists.',
            'audience' => IntranetMessageBoard::AUDIENCE_DEPARTMENTAL,
            'department_id' => $clinicalDeptId,
            'is_pinned' => false,
        ]);

        $boardPublic = IntranetMessageBoard::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'staff-welfare-social-hub',
        ], [
            'title' => 'Staff Welfare, Social Club & Wellness',
            'description' => 'Community board for wellness initiatives, institutional sports, clubs, and social gatherings.',
            'audience' => IntranetMessageBoard::AUDIENCE_PUBLIC,
            'department_id' => null,
            'is_pinned' => false,
        ]);

        // 3. Seed Posts & Replies on Executive Board
        $execPost = IntranetBoardPost::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'intranet_message_board_id' => $boardInternal->id,
            'title' => 'Executive Directive: FY2027 Accreditation Roadmap & Audit Milestones',
        ], [
            'author_id' => (string) $author->uuid,
            'content' => "All Directorate heads and Quality focal persons are requested to finalize Stage-2 documentation audits before the end of the current quarter. Complete compliance packets are available in the ISO Library.\n\nSigned,\nDirectorate of Institutional Governance",
            'is_announcement' => true,
            'is_pinned' => true,
        ]);

        IntranetBoardPost::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'intranet_message_board_id' => $boardInternal->id,
            'parent_id' => $execPost->id,
            'title' => 'Re: Executive Directive: FY2027 Accreditation Roadmap & Audit Milestones',
        ], [
            'author_id' => (string) $author->uuid,
            'content' => 'Quality assurance coordinators have verified compliance templates across all directorates.',
            'is_announcement' => false,
            'is_pinned' => false,
        ]);

        // 4. Seed Posts on Welfare Board
        IntranetBoardPost::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'intranet_message_board_id' => $boardPublic->id,
            'title' => 'Annual Staff Wellness & Health Screenings Month',
        ], [
            'author_id' => (string) $author->uuid,
            'content' => 'Complimentary biometric and cardiovascular screenings will be conducted every Tuesday and Thursday at the Occupational Health Unit.',
            'is_announcement' => true,
            'is_pinned' => false,
        ]);

        // 5. Seed Upcoming Events
        $eventTownHall = IntranetEvent::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'all-hands-institutional-town-hall',
        ], [
            'title' => 'All-Hands Institutional Town Hall & Q3 Strategic Roadmap',
            'event_date' => now()->addDays(7)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:30',
            'location' => 'Main Executive Auditorium & Virtual Livestream',
            'category' => IntranetEvent::CATEGORY_ALL_HANDS,
            'description' => 'Comprehensive review of institutional performance indicators, expansion roadmaps, and employee recognition.',
            'organizer_id' => (string) $author->uuid,
            'is_virtual' => true,
            'meeting_link' => 'https://meet.google.com/xyz-inst-townhall',
        ]);

        IntranetEvent::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'clinical-governance-patient-safety-cme',
        ], [
            'title' => 'Clinical Governance & Patient Safety Quality Workshop',
            'event_date' => now()->addDays(14)->toDateString(),
            'start_time' => '14:00',
            'end_time' => '16:30',
            'location' => 'Conference Room B, Medical Training Centre',
            'category' => IntranetEvent::CATEGORY_TRAINING,
            'description' => 'Accredited CME seminar focusing on root cause analysis, error reporting protocols, and infection control.',
            'organizer_id' => (string) $author->uuid,
            'is_virtual' => false,
        ]);

        // 6. Seed Event RSVPs
        IntranetEventRsvp::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'intranet_event_id' => $eventTownHall->id,
            'user_id' => (string) $author->uuid,
        ], [
            'status' => IntranetEventRsvp::STATUS_ATTENDING,
            'notes' => 'Attending in-person at the Main Auditorium.',
        ]);
    }
}
