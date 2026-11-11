<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo;

/** Public pages never show anything labelled as demo data. */
final class PublicVisibility
{
    public const PREFIX = 'Demo: ';

    public static function isDemo(string $name): bool
    {
        return str_starts_with($name, self::PREFIX);
    }
}
