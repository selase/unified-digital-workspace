<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Support;

use Carbon\CarbonInterface;

final class Dimensions
{
    public const LABELS = ['gender' => 'Gender', 'age_band' => 'Age band', 'location' => 'Location'];

    public const FIXED_BUCKETS = [
        'gender' => ['female' => 'Female', 'male' => 'Male', 'other' => 'Other', 'unknown' => 'Unknown'],
        'age_band' => ['0-17' => '0–17', '18-34' => '18–34', '35-59' => '35–59', '60+' => '60+', 'unknown' => 'Unknown'],
    ];

    public static function ageBand(?CarbonInterface $birthDate, CarbonInterface $periodEnd): string
    {
        if ($birthDate === null || $birthDate->gt($periodEnd)) {
            return 'unknown';
        }

        $age = $birthDate->diffInYears($periodEnd);

        return match (true) {
            $age < 18 => '0-17',
            $age < 35 => '18-34',
            $age < 60 => '35-59',
            default => '60+',
        };
    }

    public static function bucketLabel(string $dimension, string $bucket): string
    {
        return self::FIXED_BUCKETS[$dimension][$bucket] ?? ($bucket === 'unknown' ? 'Unknown' : $bucket);
    }

    public static function protect(float $value, bool $maySeeExact): float|string
    {
        return ! $maySeeExact && $value > 0 && $value < 3 ? '<3' : $value;
    }
}
