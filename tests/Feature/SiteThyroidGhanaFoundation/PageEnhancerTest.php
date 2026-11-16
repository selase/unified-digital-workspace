<?php

declare(strict_types=1);

use App\Modules\SiteThyroidGhanaFoundation\Support\PageEnhancer;

describe('FAQ accordion', function (): void {
    test('turns each question into a collapsible answer and keeps the surrounding content', function (): void {
        $html = '<p>Intro text.</p><h2>Testing</h2><h3>Do I need to fast?</h3><p>No, you <strong>do not</strong>.</p><p>Second paragraph.</p><h3>How often?</h3><p>Every six weeks.</p><h2>Support</h2><p>Closing text.</p>';

        $result = PageEnhancer::faqAccordion($html);

        expect(mb_substr_count($result, '<details'))->toBe(2)
            ->and($result)->toContain('<summary>Do I need to fast?</summary>')->toContain('<strong>do not</strong>')->toContain('Second paragraph.')
            ->toContain('<summary>How often?</summary>')->toContain('<p>Intro text.</p>')->toContain('<h2>Testing</h2>')->toContain('<h2>Support</h2>')->toContain('<p>Closing text.</p>');
        $closing = mb_strpos($result, '<h2>Support</h2>');
        expect(mb_strrpos(mb_substr($result, 0, $closing), '</details>'))->toBeGreaterThan(mb_strrpos(mb_substr($result, 0, $closing), 'Every six weeks.'));
    });

    test('leaves a page without questions untouched and escapes question text', function (): void {
        expect(PageEnhancer::faqAccordion('<p>Just text.</p>'))->toBe('<p>Just text.</p>')
            ->and(PageEnhancer::faqAccordion('<h3>Is 5 &lt; 6 &amp; true?</h3><p>Yes.</p>'))->toContain('<summary>Is 5 &lt; 6 &amp; true?</summary>');
    });
});

describe('Statistic tiles', function (): void {
    test('turns the Key Statistics list into tiles with a value and a label', function (): void {
        $html = '<p>Before.</p><h2>Key Statistics</h2><ul><li><strong>1,300 cases</strong> reported during the study period</li><li>Age range: <strong>1 to 86 years</strong>, with peak incidence in the 30-39 age group</li></ul><h2>Next</h2><p>After.</p>';

        $result = PageEnhancer::statTiles($html);

        expect($result)->toContain('class="tgf-stats"')->and(mb_substr_count($result, 'class="tgf-stat"'))->toBe(2)
            ->and($result)->toContain('<strong>1,300 cases</strong>')->toContain('reported during the study period')
            ->toContain('<strong>1 to 86 years</strong>')->toContain('Age range, with peak incidence in the 30-39 age group')
            ->toContain('<p>Before.</p>')->toContain('<h2>Next</h2>')->toContain('<p>After.</p>')->not->toContain('<ul>');
    });

    test('keeps the original list when an item has no figure or the heading is missing', function (): void {
        $noFigure = '<h2>Key Statistics</h2><ul><li>No figure here</li></ul>';

        expect(PageEnhancer::statTiles($noFigure))->toBe($noFigure)
            ->and(PageEnhancer::statTiles('<ul><li><strong>5</strong> things</li></ul>'))->toBe('<ul><li><strong>5</strong> things</li></ul>');
    });
});

describe('Routing by page', function (): void {
    test('applies the right enhancement for the faq and challenge pages only', function (): void {
        $faq = '<h3>Q?</h3><p>A.</p>';
        $stats = '<h2>Key Statistics</h2><ul><li><strong>9</strong> things</li></ul>';

        expect(PageEnhancer::apply('faq', $faq))->toContain('<details')
            ->and(PageEnhancer::apply('the-challenge', $stats))->toContain('tgf-stat')
            ->and(PageEnhancer::apply('about', $faq))->toBe($faq);
    });
});
