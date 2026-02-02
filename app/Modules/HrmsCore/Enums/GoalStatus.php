<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Enums;

enum GoalStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Deferred = 'deferred';
    case Cancelled = 'cancelled';

    /**
     * Get the display label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Deferred => 'Deferred',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get the CSS class for styling this status.
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::NotStarted => 'bg-gray-100 text-gray-800',
            self::InProgress => 'bg-blue-100 text-blue-800',
            self::Completed => 'bg-green-100 text-green-800',
            self::Deferred => 'bg-yellow-100 text-yellow-800',
            self::Cancelled => 'bg-red-100 text-red-800',
        };
    }

    /**
     * Check if this is a final status.
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Deferred,
            self::Cancelled,
        ], true);
    }

    /**
     * Get all statuses as options for forms.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
