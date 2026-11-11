<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo;

use App\Models\Tenant;
use Carbon\CarbonImmutable;

interface DemoSection
{
    public function label(): string;

    public function seed(Tenant $tenant): void;

    /** Deletes the rows this section created at or after $since and returns how many were removed. */
    public function remove(Tenant $tenant, CarbonImmutable $since): int;
}
