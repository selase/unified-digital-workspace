<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Database\Seeders;

use App\Models\User;
use App\Modules\DocumentManagement\Models\Document;
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

        $owner = User::query()
            ->where('tenant_id', $tenant->id)
            ->first() ?? User::query()->first();

        if (! $owner) {
            return;
        }

        $document = Document::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'employee-handbook',
        ], [
            'title' => 'Employee Handbook',
            'description' => 'Core operating procedures for all teams.',
            'status' => 'published',
            'owner_id' => (string) $owner->uuid,
            'category' => 'policy',
            'tags' => ['hr', 'operations'],
            'visibility' => [
                'tenant_wide' => true,
                'is_private' => false,
            ],
            'published_at' => now()->subDays(7),
        ]);

        $versionOne = DocumentVersion::query()->updateOrCreate([
            'document_id' => $document->id,
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
            'document_id' => $document->id,
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

        if ((int) $document->current_version_id !== (int) $versionTwo->id) {
            $document->forceFill(['current_version_id' => $versionTwo->id])->save();
        }

        $quiz = DocumentQuiz::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
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
                'A' => 'Public forum channel',
                'B' => 'Incident management module',
                'C' => 'Personal email',
            ],
            'correct_option' => 'B',
            'points' => 1,
        ]);

        DocumentQuizQuestion::query()->updateOrCreate([
            'quiz_id' => $quiz->id,
            'sort_order' => 2,
        ], [
            'tenant_id' => $tenant->id,
            'body' => 'Who can access tenant-wide policy documents?',
            'options' => [
                'A' => 'Only Org Superadmin',
                'B' => 'All users in the tenant',
                'C' => 'Only HR',
            ],
            'correct_option' => 'B',
            'points' => 1,
        ]);

        DocumentQuizQuestion::query()->updateOrCreate([
            'quiz_id' => $quiz->id,
            'sort_order' => 3,
        ], [
            'tenant_id' => $tenant->id,
            'body' => 'What should happen after a handbook update?',
            'options' => [
                'A' => 'No action',
                'B' => 'Republish and notify users',
                'C' => 'Delete prior versions',
            ],
            'correct_option' => 'B',
            'points' => 2,
        ]);

        DocumentQuizAttempt::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'quiz_id' => $quiz->id,
            'user_id' => (string) $owner->uuid,
            'completed_at' => now()->subDay()->startOfHour(),
        ], [
            'score' => 4,
            'responses' => [
                'q1' => 'B',
                'q2' => 'B',
                'q3' => 'B',
            ],
            'started_at' => now()->subDay()->subMinutes(20),
        ]);

        DocumentQuizAttempt::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'quiz_id' => $quiz->id,
            'user_id' => (string) $owner->uuid,
            'completed_at' => now()->subHours(6)->startOfHour(),
        ], [
            'score' => 3,
            'responses' => [
                'q1' => 'B',
                'q2' => 'A',
                'q3' => 'B',
            ],
            'started_at' => now()->subHours(6)->subMinutes(12),
        ]);

        DocumentAudit::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'event' => 'document.created',
        ], [
            'user_id' => (string) $owner->uuid,
            'metadata' => [
                'source' => 'seeder',
            ],
            'created_at' => now()->subDays(7),
        ]);

        DocumentAudit::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'event' => 'document.version_uploaded',
        ], [
            'user_id' => (string) $owner->uuid,
            'metadata' => [
                'version' => $versionTwo->version_number,
                'source' => 'seeder',
            ],
            'created_at' => now()->subDays(2),
        ]);

        DocumentAudit::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'event' => 'document.quiz_attempted',
        ], [
            'user_id' => (string) $owner->uuid,
            'metadata' => [
                'quiz_id' => $quiz->id,
                'source' => 'seeder',
            ],
            'created_at' => now()->subHours(6),
        ]);

        Document::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'incident-playbook',
        ], [
            'title' => 'Incident Response Playbook',
            'description' => 'Playbook for handling and escalating incidents.',
            'status' => 'published',
            'owner_id' => (string) $owner->uuid,
            'category' => 'playbook',
            'tags' => ['incident', 'security'],
            'visibility' => [
                'tenant_wide' => true,
                'is_private' => false,
            ],
            'published_at' => now()->subDays(3),
        ]);

        Document::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'memo-compliance-guide',
        ], [
            'title' => 'Memo Compliance Guide',
            'description' => 'Guidance for signed memo flows and recipient acknowledgements.',
            'status' => 'draft',
            'owner_id' => (string) $owner->uuid,
            'category' => 'guideline',
            'tags' => ['memos', 'compliance'],
            'visibility' => [
                'tenant_wide' => false,
                'is_private' => false,
            ],
        ]);
    }
}
