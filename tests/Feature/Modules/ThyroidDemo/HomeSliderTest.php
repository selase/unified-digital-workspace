<?php

declare(strict_types=1);

require_once __DIR__.'/SiteTenantHelper.php';

test('hero slides take their visible and clickable state from one attribute the carousel replaces', function (): void {
    tgfSiteTenant();

    $html = $this->get(TGF_SITE.'/')->assertOk()->getContent();
    preg_match_all('/<div\s[^>]*?:data-active="current === (\d+) \? \'true\' : \'false\'"[^>]*>/s', $html, $matches);
    $tags = $matches[0];

    expect(count($tags))->toBeGreaterThanOrEqual(2);
    foreach ($tags as $index => $tag) {
        preg_match('/\sclass="([^"]*)"/', $tag, $class);
        preg_match('/\sdata-active="([^"]*)"/', $tag, $initial);

        expect(array_intersect(explode(' ', $class[1]), ['opacity-100', 'z-10', 'pointer-events-auto']))->toBe([])
            ->and($class[1])->toContain('data-[active=true]:opacity-100')->toContain('data-[active=true]:pointer-events-auto')
            ->and($initial[1])->toBe($index === 0 ? 'true' : 'false');
    }
});
