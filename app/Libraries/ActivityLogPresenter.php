<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\User;
use App\Models\UserLoginHistory;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Turns raw audit-trail rows into sentences a non-technical person can read.
 * Field values are deliberately left out: the profile page is not the place
 * to replay record contents.
 */
final class ActivityLogPresenter
{
    /**
     * Bookkeeping columns that change on every save and say nothing useful.
     *
     * @var array<int, string>
     */
    private const IGNORED_FIELDS = [
        'id', 'uuid', 'tenant_id', 'created_at', 'updated_at', 'deleted_at',
        'remember_token', 'password', 'last_login_at', 'last_login_ip',
    ];

    /**
     * @return array{summary: string, details: string|null}
     */
    public static function describe(Activity $activity): array
    {
        if ($activity->subject_type === UserLoginHistory::class) {
            return ['summary' => 'Signed in', 'details' => null];
        }

        $changedFields = self::changedFields($activity);

        if ($activity->subject_type === User::class && $activity->event === 'updated' && $changedFields === []) {
            return ['summary' => 'Sign-in recorded', 'details' => null];
        }

        $subject = self::subjectLabel($activity->subject_type);

        $summary = match ($activity->event) {
            'created' => "Created {$subject}",
            'updated' => "Updated {$subject}",
            'deleted' => "Deleted {$subject}",
            'restored' => "Restored {$subject}",
            default => Str::ucfirst((string) $activity->description),
        };

        $details = $activity->event === 'updated' && $changedFields !== []
            ? 'Changed: '.implode(', ', $changedFields)
            : null;

        return ['summary' => $summary, 'details' => $details];
    }

    private static function subjectLabel(?string $subjectType): string
    {
        if (blank($subjectType)) {
            return 'a record';
        }

        $label = Str::lower(Str::headline(class_basename($subjectType)));

        return (in_array($label[0], ['a', 'e', 'i', 'o', 'u'], true) ? 'an ' : 'a ').$label;
    }

    /**
     * @return array<int, string>
     */
    private static function changedFields(Activity $activity): array
    {
        $attributes = (array) ($activity->properties['attributes'] ?? []);

        return collect(array_keys($attributes))
            ->reject(fn (string $field): bool => in_array($field, self::IGNORED_FIELDS, true))
            ->map(fn (string $field): string => Str::lower(Str::headline($field)))
            ->values()
            ->all();
    }
}
