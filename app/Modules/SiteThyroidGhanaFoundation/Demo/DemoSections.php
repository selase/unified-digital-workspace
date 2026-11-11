<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo;

use App\Modules\SiteThyroidGhanaFoundation\Demo\Sections\BeneficiariesSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\Sections\DonationsSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\Sections\FinanceSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\Sections\GovernanceSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\Sections\GrantsSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\Sections\MembershipsSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\Sections\ResultsSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\Sections\VolunteersSection;

/** The demo sections in dependency order: finance first, so later sections can post to the ledger. */
final class DemoSections
{
    /** @param  list<DemoSection>|null  $sections */
    public function __construct(private readonly ?array $sections = null) {}

    /** @return list<DemoSection> */
    public function all(): array
    {
        return $this->sections ?? [new FinanceSection, new MembershipsSection, new DonationsSection, new GrantsSection, new VolunteersSection, new BeneficiariesSection, new GovernanceSection, new ResultsSection];
    }
}
