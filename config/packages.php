<?php

declare(strict_types=1);

$community = ['core', 'cms-core', 'forms'];
$website = [...$community, 'intranet'];
$team = [...$website, 'crm', 'document-management', 'memos', 'forums', 'project-management', 'reports', 'workflow-automation'];
$institution = [...$team, 'hrms-core', 'incident-management', 'quality-monitoring', 'facilities-management'];

return [
    'plans' => [
        'community' => ['name' => 'Community', 'modules' => $community, 'features' => [], 'description' => 'Usage-based, no monthly base charge. Core workspace, CMS and forms.'],
        'website' => ['name' => 'Website', 'modules' => $website, 'features' => ['custom-domains'], 'description' => 'Usage-based, no monthly base charge. Community plus intranet and custom domains.'],
        'team' => ['name' => 'Team', 'modules' => $team, 'features' => ['custom-domains'], 'description' => 'Usage-based, no monthly base charge. Website plus collaboration and business workflows.'],
        'institution' => ['name' => 'Institution', 'modules' => $institution, 'features' => ['custom-domains'], 'description' => 'Usage-based, no monthly base charge. Team plus HR, incidents, quality and facilities. Dedicated database available as an option.'],
        'marketplace' => ['name' => 'Marketplace', 'modules' => [...$institution, 'real-estate', 'vendor-marketplace', 'store'], 'features' => ['custom-domains', 'commerce'], 'description' => 'Usage-based, no monthly base charge. Institution plus real estate, vendor marketplace and store.'],
    ],
    'legacy_aliases' => ['starter' => 'community', 'pro' => 'team', 'enterprise' => 'institution'],
    'retired_features' => ['sso', 'file-retention', 'custom-alerts'],
];
