<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

final class Money
{
    /** "1,250.50" -> 125050 (minor units). Rounds half up. */
    public static function toMinor(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        $clean = str_replace([',', ' '], '', (string) $amount);

        return (int) round(((float) $clean) * 100);
    }

    public static function format(int $minor, string $currency = 'GHS'): string
    {
        $sign = $minor < 0 ? '-' : '';

        return mb_trim($sign.$currency.' '.number_format(abs($minor) / 100, 2));
    }

    /** Plain decimal for form fields, without thousands separators. */
    public static function input(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }
}
