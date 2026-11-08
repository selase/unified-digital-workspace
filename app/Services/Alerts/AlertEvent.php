<?php

declare(strict_types=1);

namespace App\Services\Alerts;

/**
 * The things a tenant can be alerted about. Adding an event means adding it here and
 * calling AlertDispatcher::dispatch() where it happens.
 */
final class AlertEvent
{
    public const USAGE_LIMIT = 'usage.limit_reached';

    public const INVOICE_OVERDUE = 'billing.invoice_overdue';

    public const FORM_SUBMISSION = 'forms.submission_received';

    public const DOCUMENT_REVIEW_DUE = 'documents.review_due';

    public const CREDITS_LOW = 'credits.low_balance';

    public const JOB_APPLICATION = 'hrms.application_received';

    /** @return array<string, array{label: string, description: string}> */
    public static function all(): array
    {
        return [
            self::USAGE_LIMIT => ['label' => 'Usage limit reached', 'description' => 'Your organisation reaches a usage limit, such as storage or active users.'],
            self::INVOICE_OVERDUE => ['label' => 'Invoice overdue', 'description' => 'An invoice for your subscription is past its due date.'],
            self::FORM_SUBMISSION => ['label' => 'Form submission received', 'description' => 'Someone submits one of your forms.'],
            self::DOCUMENT_REVIEW_DUE => ['label' => 'Document review or expiry due', 'description' => 'A controlled document needs review or is about to expire.'],
            self::CREDITS_LOW => ['label' => 'Credit balance low', 'description' => 'Your SMS, email or AI credits are running out.'],
            self::JOB_APPLICATION => ['label' => 'Job application received', 'description' => 'Someone applies for a job on your careers page.'],
        ];
    }

    public static function exists(string $event): bool
    {
        return array_key_exists($event, self::all());
    }

    /** @return list<string> */
    public static function channels(): array
    {
        return ['mail', 'sms', 'database'];
    }
}
