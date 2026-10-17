<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Database\Seeders;

use App\Models\User;
use App\Modules\DocumentManagement\Models\Document;
use App\Modules\DocumentManagement\Models\DocumentAcknowledgement;
use App\Modules\DocumentManagement\Models\DocumentAudit;
use App\Modules\DocumentManagement\Models\DocumentQuiz;
use App\Modules\DocumentManagement\Models\DocumentQuizAttempt;
use App\Modules\DocumentManagement\Models\DocumentQuizQuestion;
use App\Modules\DocumentManagement\Models\DocumentVersion;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

final class DocumentManagementSeeder extends Seeder
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

        if (! Schema::connection($tenantConnection)->hasTable('documents')) {
            return;
        }

        $users = User::query()
            ->where('tenant_id', $tenant->id)
            ->get();

        $owner = $users->first() ?? User::query()->first();

        if (! $owner) {
            return;
        }

        $reviewer = $users->skip(1)->first() ?? $owner;
        $approver = $users->skip(2)->first() ?? $owner;

        // 1. Published Controlled Master: Employee Operating Handbook
        $handbook = Document::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'employee-handbook',
        ], [
            'title' => 'Employee Handbook',
            'description' => 'Core operating procedures for all clinical and administrative teams.',
            'status' => Document::STATUS_PUBLISHED,
            'owner_id' => (string) $owner->uuid,
            'reviewer_id' => (string) $reviewer->uuid,
            'approver_id' => (string) $approver->uuid,
            'review_due_at' => now()->addMonths(6),
            'expires_at' => now()->addYears(2),
            'category' => 'Institutional Policy',
            'tags' => ['hr', 'operations'],
            'visibility' => [
                'tenant_wide' => true,
                'is_private' => false,
            ],
            'published_at' => now()->subDays(14),
        ]);

        $versionOne = DocumentVersion::query()->updateOrCreate([
            'document_id' => $handbook->id,
            'version_number' => 1,
        ], [
            'tenant_id' => $tenant->id,
            'disk' => 'public',
            'path' => 'documents/employee-handbook/v1.pdf',
            'filename' => 'employee-handbook-v1.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 512_000,
            'checksum_sha256' => hash('sha256', 'employee-handbook-v1.pdf'),
            'uploaded_by_id' => (string) $owner->uuid,
            'notes' => 'Initial handbook publication.',
        ]);

        $versionTwo = DocumentVersion::query()->updateOrCreate([
            'document_id' => $handbook->id,
            'version_number' => 2,
        ], [
            'tenant_id' => $tenant->id,
            'disk' => 'public',
            'path' => 'documents/employee-handbook/v2.pdf',
            'filename' => 'employee-handbook-v2.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 734_003,
            'checksum_sha256' => hash('sha256', 'employee-handbook-v2.pdf'),
            'uploaded_by_id' => (string) $owner->uuid,
            'notes' => 'Expanded remote work and incident response sections.',
        ]);

        if ((int) $handbook->current_version_id !== (int) $versionTwo->id) {
            $handbook->forceFill(['current_version_id' => $versionTwo->id])->save();
        }

        // Read Acknowledgements for Published Handbook
        foreach ($users->take(3) as $u) {
            DocumentAcknowledgement::query()->updateOrCreate([
                'document_id' => $handbook->id,
                'user_id' => (string) $u->uuid,
            ], [
                'tenant_id' => $tenant->id,
                'acknowledged_at' => now()->subDays(5),
                'notes' => 'Mandatory annual policy sign-off.',
            ]);
        }

        // Quiz for Handbook
        $quiz = DocumentQuiz::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'document_id' => $handbook->id,
            'title' => 'Handbook Readiness Quiz',
        ], [
            'description' => 'Quick readiness assessment after reading the handbook.',
            'settings' => [
                'pass_percentage' => 70,
                'pass_score' => 3,
                'shuffle_questions' => false,
            ],
        ]);

        DocumentQuizQuestion::query()->updateOrCreate([
            'quiz_id' => $quiz->id,
            'sort_order' => 1,
        ], [
            'tenant_id' => $tenant->id,
            'body' => 'Where should staff report a security incident?',
            'options' => [
                'Public forum channel',
                'Incident management module',
                'Personal email',
            ],
            'correct_option' => '1',
            'points' => 1,
        ]);

        DocumentQuizQuestion::query()->updateOrCreate([
            'quiz_id' => $quiz->id,
            'sort_order' => 2,
        ], [
            'tenant_id' => $tenant->id,
            'body' => 'What is the required approval workflow for travel expenses?',
            'options' => [
                'Verbal consent only',
                'Directorate sign-off and finance voucher',
                'No approvals needed under GHS 500',
            ],
            'correct_option' => '1',
            'points' => 1,
        ]);

        DocumentQuizAttempt::query()->updateOrCreate([
            'quiz_id' => $quiz->id,
            'user_id' => (string) $owner->uuid,
        ], [
            'tenant_id' => $tenant->id,
            'score' => 2,
            'responses' => [
                ['question_id' => 1, 'selected_answer' => '1', 'is_correct' => true],
                ['question_id' => 2, 'selected_answer' => '1', 'is_correct' => true],
            ],
            'started_at' => now()->subHours(6)->subMinutes(12),
            'completed_at' => now()->subHours(6),
        ]);

        // 2. In Review: Emergency Code Response & Resuscitation Protocol
        $inReviewDoc = Document::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'emergency-code-response-protocol',
        ], [
            'title' => 'Emergency Code Response & Resuscitation Protocol',
            'description' => 'Hospital-wide rapid response team protocols for Code Blue and Code Red activations.',
            'status' => Document::STATUS_IN_REVIEW,
            'owner_id' => (string) $owner->uuid,
            'reviewer_id' => (string) $reviewer->uuid,
            'approver_id' => (string) $approver->uuid,
            'review_due_at' => now()->addDays(5),
            'expires_at' => now()->addYears(1),
            'category' => 'Standard Operating Procedure (SOP)',
            'tags' => ['emergency', 'clinical'],
            'visibility' => ['tenant_wide' => true],
        ]);

        // 3. Approved: Hospital Radiopharmacy Safety Standard
        $approvedDoc = Document::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'radiopharmacy-safety-standard',
        ], [
            'title' => 'Hospital Radiopharmacy Safety Standard',
            'description' => 'Radiation protection, dosimetry monitoring, and isotope handling standards.',
            'status' => Document::STATUS_APPROVED,
            'owner_id' => (string) $owner->uuid,
            'reviewer_id' => (string) $reviewer->uuid,
            'approver_id' => (string) $approver->uuid,
            'review_due_at' => now()->subDays(2),
            'expires_at' => now()->addYears(2),
            'review_comments' => 'Peer review complete. Approved for catalog publication.',
            'category' => 'Technical Specification',
            'tags' => ['radiology', 'safety'],
            'visibility' => ['tenant_wide' => true],
        ]);

        // 4. Draft: Telemedicine Clinical Consultation Guideline
        Document::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'telemedicine-clinical-guideline',
        ], [
            'title' => 'Telemedicine Clinical Consultation Guideline',
            'description' => 'Standards for remote patient encounters, video charting, and prescription issuance.',
            'status' => Document::STATUS_DRAFT,
            'owner_id' => (string) $owner->uuid,
            'category' => 'Clinical Practice Guideline',
            'tags' => ['telehealth', 'clinical'],
            'visibility' => ['tenant_wide' => true],
        ]);

        // 5. Under Revision: Patient Blood Management & Transfusion Protocol
        Document::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'blood-transfusion-protocol',
        ], [
            'title' => 'Patient Blood Management & Transfusion Protocol',
            'description' => 'Cross-matching, cold-chain blood transport, and bedside verification protocol.',
            'status' => Document::STATUS_UNDER_REVISION,
            'owner_id' => (string) $owner->uuid,
            'reviewer_id' => (string) $reviewer->uuid,
            'review_comments' => 'Needs alignment with the latest national blood bank storage thresholds.',
            'category' => 'Standard Operating Procedure (SOP)',
            'tags' => ['hematology', 'transfusion'],
            'visibility' => ['tenant_wide' => true],
        ]);

        // 6. Obsolete: Legacy Ward Sanitation Standard (2020)
        Document::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'ward-sanitation-standard-2020',
        ], [
            'title' => 'Ward Sanitation Standard (2020)',
            'description' => 'Superseded ward cleaning and disinfection guidelines.',
            'status' => Document::STATUS_OBSOLETE,
            'owner_id' => (string) $owner->uuid,
            'superseded_by_id' => $handbook->id,
            'category' => 'Administrative Manual',
            'tags' => ['sanitation', 'legacy'],
            'visibility' => ['tenant_wide' => true],
        ]);

        // Audit Trail Entries
        DocumentAudit::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'document_id' => $handbook->id,
            'event' => 'create',
        ], [
            'user_id' => (string) $owner->uuid,
            'metadata' => ['title' => $handbook->title, 'source' => 'seeder'],
            'created_at' => now()->subDays(14),
        ]);

        DocumentAudit::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'document_id' => $handbook->id,
            'event' => 'publish',
        ], [
            'user_id' => (string) $approver->uuid,
            'metadata' => ['status' => 'published'],
            'created_at' => now()->subDays(14),
        ]);

        DocumentAudit::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'document_id' => $inReviewDoc->id,
            'event' => 'submitted_for_review',
        ], [
            'user_id' => (string) $owner->uuid,
            'metadata' => ['reviewer_id' => (string) $reviewer->uuid],
            'created_at' => now()->subDays(1),
        ]);

        DocumentAudit::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'document_id' => $approvedDoc->id,
            'event' => 'approved',
        ], [
            'user_id' => (string) $approver->uuid,
            'metadata' => ['approver_id' => (string) $approver->uuid],
            'created_at' => now()->subDays(2),
        ]);
    }
}
