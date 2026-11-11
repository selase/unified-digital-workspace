<?php

declare(strict_types=1);

require_once __DIR__.'/SiteTenantHelper.php';

describe('Existing live pages keep their content', function (): void {
    test('the team page still shows every team member and advisor', function (): void {
        tgfSiteTenant();

        $html = $this->get(TGF_SITE.'/our-team')->assertOk()->getContent();

        expect($html)->toContain('Dr. Nana Adwoa Konadu Dsane')->toContain('Rev. Prof. Patrick F. Ayeh-Kumi')->toContain('Mr. Leslie Chartey Kumahlor')
            ->toContain('Dr. Joyce Emefa Addo-Klah')->toContain('Frank Anyimadu')->toContain('Justice Kwesi Baah')
            ->toContain('Dr. Josephine Akpalu')->toContain('Dr. Alfred Tetteh')->toContain('Dr. Matilda Asante');
    });

    test('the home page still shows the four headline figures and four pillars', function (): void {
        tgfSiteTenant();

        $html = $this->get(TGF_SITE.'/')->assertOk()->getContent();

        expect($html)->toContain('Since 2018')->toContain('1,300+')->toContain('87.8%')->toContain('West Africa')
            ->toContain('Surgery Access')->toContain('Patient Forums')->toContain('Medicine Access')->toContain('Research &amp; Fundraising');
    });
});

describe('Our Board page', function (): void {
    test('lists the management board and the advisory board with their roles', function (): void {
        tgfSiteTenant();

        $html = $this->get(TGF_SITE.'/our-board')->assertOk()->getContent();

        expect($html)->toContain('Rev. Prof. Patrick F. Ayeh-Kumi')->toContain('Management Board Chair')
            ->toContain('Dr. Alfred Tetteh')->toContain('Dr. Matilda Asante')
            ->toContain('Dr. Josephine Akpalu')->toContain('Advisory Board Chair')
            ->not->toContain('Demo:');
    });
});

describe('Our Impact page', function (): void {
    test('shows the headline figures and the four pillars', function (): void {
        tgfSiteTenant();

        $html = $this->get(TGF_SITE.'/our-impact')->assertOk()->getContent();

        expect($html)->toContain('Since 2018')->toContain('1,300+')->toContain('87.8%')->toContain('West Africa')
            ->toContain('Surgery Access')->toContain('Research &amp; Fundraising')->not->toContain('Demo:');
    });
});

describe('About links', function (): void {
    test('Our Board and Our Impact are linked from the header, mobile menu and footer', function (): void {
        tgfSiteTenant();

        $html = $this->get(TGF_SITE.'/our-team')->assertOk()->getContent();

        expect(mb_substr_count($html, 'href="/our-board"'))->toBeGreaterThanOrEqual(3)->and(mb_substr_count($html, 'href="/our-impact"'))->toBeGreaterThanOrEqual(3);
    });
});
