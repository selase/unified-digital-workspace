<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo;

/** The demo sections in dependency order: finance first, so later sections can post to the ledger. */
final class DemoSections
{
    /** @param  list<DemoSection>|null  $sections */
    public function __construct(private readonly ?array $sections = null) {}

    /** @return list<DemoSection> */
    public function all(): array
    {
        return $this->sections ?? [];
    }
}
