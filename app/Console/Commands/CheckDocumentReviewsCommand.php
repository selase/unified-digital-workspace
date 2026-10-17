<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\DocumentManagement\Models\Document;
use App\Modules\DocumentManagement\Models\DocumentAudit;
use App\Notifications\InstitutionalWorkflowAlert;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

final class CheckDocumentReviewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:check-reviews {--tenant= : Scope to specific tenant ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check ISO controlled document review deadlines and expiration dates, dispatching alerts and logging audit trails';

    public function handle(TenantContext $tenantContext, TenantDatabaseManager $dbManager): int
    {
        $this->info('Starting ISO controlled document review and expiration check...');

        $tenantId = $this->option('tenant');
        $query = Tenant::query()->where('status', 'active');
        if (! empty($tenantId)) {
            $query->where('id', (string) $tenantId);
        }
        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $dbManager->configureShared();
            $this->checkTenantDocuments(null);
        } else {
            foreach ($tenants as $tenant) {
                $tenantContext->setTenant($tenant);
                $dbManager->configure($tenant);
                $this->checkTenantDocuments($tenant);
            }
        }

        return self::SUCCESS;
    }

    private function checkTenantDocuments(?Tenant $tenant): void
    {
        $now = now();
        $dueSoonThreshold = $now->copy()->addDays(3);
        $expirySoonThreshold = $now->copy()->addDays(14);

        // 1. In Review documents (overdue or approaching deadline)
        $inReviewQuery = Document::query()
            ->where('status', Document::STATUS_IN_REVIEW)
            ->whereNotNull('review_due_at')
            ->where('review_due_at', '<=', $dueSoonThreshold)
            ->with(['reviewer', 'owner']);

        if ($tenant) {
            $inReviewQuery->where('tenant_id', $tenant->id);
        }

        $inReviewDocs = $inReviewQuery->get();

        $remindersSent = 0;
        foreach ($inReviewDocs as $doc) {
            $isOverdue = $doc->review_due_at->isPast();
            $subject = $isOverdue
                ? "ISO Review Overdue: {$doc->title}"
                : "ISO Review Due Soon: {$doc->title}";

            $message = $isOverdue
                ? "Document review was due on {$doc->review_due_at->format('d/m/Y')}. Immediate review required."
                : "Document review is due on {$doc->review_due_at->format('d/m/Y')}. Please complete your review.";

            // Target reviewer, then owner
            $recipient = $doc->reviewer ?? $doc->owner;

            if ($recipient instanceof User) {
                // Avoid spamming if reminder already logged in audits today
                $alreadyLoggedToday = DocumentAudit::query()
                    ->where('document_id', $doc->id)
                    ->where('event', 'review_reminder_sent')
                    ->whereDate('created_at', $now->toDateString())
                    ->exists();

                if (! $alreadyLoggedToday) {
                    Notification::send($recipient, new InstitutionalWorkflowAlert(
                        title: $subject,
                        message: $message,
                        workflowRuleId: 0,
                        actionUrl: route('document-management.documents.show', $doc)
                    ));

                    DocumentAudit::create([
                        'tenant_id' => $doc->tenant_id,
                        'document_id' => $doc->id,
                        'user_id' => (string) $recipient->uuid,
                        'event' => 'review_reminder_sent',
                        'metadata' => [
                            'is_overdue' => $isOverdue,
                            'due_at' => $doc->review_due_at->toIso8601String(),
                        ],
                        'created_at' => $now,
                    ]);

                    $remindersSent++;
                }
            }
        }

        // 2. Published documents approaching or past expiration
        $expiringQuery = Document::query()
            ->where('status', Document::STATUS_PUBLISHED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $expirySoonThreshold)
            ->with('owner');

        if ($tenant) {
            $expiringQuery->where('tenant_id', $tenant->id);
        }

        $expiringDocs = $expiringQuery->get();

        $expiriesNotified = 0;
        foreach ($expiringDocs as $doc) {
            $isExpired = $doc->expires_at->isPast();
            $subject = $isExpired
                ? "Document Expired: {$doc->title}"
                : "Document Expiring Soon: {$doc->title}";

            $message = $isExpired
                ? "Controlled document expired on {$doc->expires_at->format('d/m/Y')}. Please initiate revision or mark obsolete."
                : "Controlled document will expire on {$doc->expires_at->format('d/m/Y')}. Please plan revision.";

            if ($doc->owner instanceof User) {
                $alreadyNotified = DocumentAudit::query()
                    ->where('document_id', $doc->id)
                    ->where('event', 'expiry_reminder_sent')
                    ->whereDate('created_at', $now->toDateString())
                    ->exists();

                if (! $alreadyNotified) {
                    Notification::send($doc->owner, new InstitutionalWorkflowAlert(
                        title: $subject,
                        message: $message,
                        workflowRuleId: 0,
                        actionUrl: route('document-management.documents.show', $doc)
                    ));

                    DocumentAudit::create([
                        'tenant_id' => $doc->tenant_id,
                        'document_id' => $doc->id,
                        'user_id' => (string) $doc->owner->uuid,
                        'event' => 'expiry_reminder_sent',
                        'metadata' => [
                            'is_expired' => $isExpired,
                            'expires_at' => $doc->expires_at?->toIso8601String(),
                        ],
                        'created_at' => $now,
                    ]);

                    $expiriesNotified++;
                }
            }
        }

        $this->info("Completed. {$remindersSent} review reminder(s) and {$expiriesNotified} expiry alert(s) dispatched.");
    }
}
