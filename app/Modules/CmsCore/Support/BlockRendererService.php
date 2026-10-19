<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Support;

/**
 * Validates, sanitizes, and renders visual layout blocks for CMS pages.
 */
final class BlockRendererService
{
    /**
     * Allowlisted block types.
     */
    public const ALLOWED_TYPES = [
        'hero',
        'rich_text',
        'feature_cards',
        'stats_grid',
        'faq_accordion',
        'cta_banner',
        'embedded_form',
        'property_listings_grid',
    ];

    /**
     * Sanitizes an array or JSON string of layout blocks.
     *
     * @param  array<int, array<string, mixed>>|string|null  $blocks
     * @return array<int, array<string, mixed>>
     */
    public static function sanitizeBlocks(mixed $blocks): array
    {
        if (is_string($blocks)) {
            $decoded = json_decode($blocks, true);
            $blocks = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($blocks)) {
            return [];
        }

        $sanitized = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || empty($block['type']) || ! in_array($block['type'], self::ALLOWED_TYPES, true)) {
                continue;
            }

            $type = (string) $block['type'];
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            $sanitizedData = match ($type) {
                'hero' => [
                    'title' => self::stripMaliciousTags((string) ($data['title'] ?? '')),
                    'subtitle' => self::stripMaliciousTags((string) ($data['subtitle'] ?? '')),
                    'cta_label' => self::stripMaliciousTags((string) ($data['cta_label'] ?? '')),
                    'cta_url' => self::sanitizeUrl((string) ($data['cta_url'] ?? '')),
                ],
                'rich_text' => [
                    'content' => ContentSanitizer::html((string) ($data['content'] ?? '')),
                ],
                'feature_cards' => [
                    'items' => array_map(function ($item): array {
                        $itemData = is_array($item) ? $item : [];

                        return [
                            'title' => self::stripMaliciousTags((string) ($itemData['title'] ?? '')),
                            'description' => self::stripMaliciousTags((string) ($itemData['description'] ?? '')),
                            'icon' => self::stripMaliciousTags((string) ($itemData['icon'] ?? 'check-circle')),
                        ];
                    }, is_array($data['items'] ?? null) ? $data['items'] : []),
                ],
                'stats_grid' => [
                    'items' => array_map(function ($item): array {
                        $itemData = is_array($item) ? $item : [];

                        return [
                            'label' => self::stripMaliciousTags((string) ($itemData['label'] ?? '')),
                            'value' => self::stripMaliciousTags((string) ($itemData['value'] ?? '')),
                            'suffix' => self::stripMaliciousTags((string) ($itemData['suffix'] ?? '')),
                        ];
                    }, is_array($data['items'] ?? null) ? $data['items'] : []),
                ],
                'faq_accordion' => [
                    'items' => array_map(function ($item): array {
                        $itemData = is_array($item) ? $item : [];

                        return [
                            'question' => self::stripMaliciousTags((string) ($itemData['question'] ?? '')),
                            'answer' => ContentSanitizer::html((string) ($itemData['answer'] ?? '')),
                        ];
                    }, is_array($data['items'] ?? null) ? $data['items'] : []),
                ],
                'cta_banner' => [
                    'headline' => self::stripMaliciousTags((string) ($data['headline'] ?? '')),
                    'description' => self::stripMaliciousTags((string) ($data['description'] ?? '')),
                    'button_text' => self::stripMaliciousTags((string) ($data['button_text'] ?? '')),
                    'button_url' => self::sanitizeUrl((string) ($data['button_url'] ?? '')),
                ],
                'embedded_form' => [
                    'form_id' => (int) ($data['form_id'] ?? 0),
                    'title' => self::stripMaliciousTags((string) ($data['title'] ?? '')),
                ],
                'property_listings_grid' => [
                    'title' => self::stripMaliciousTags((string) ($data['title'] ?? 'Available Properties')),
                    'limit' => min(20, max(1, (int) ($data['limit'] ?? 6))),
                ],
            };

            $sanitized[] = [
                'type' => $type,
                'data' => $sanitizedData,
            ];
        }

        return $sanitized;
    }

    /**
     * Render blocks to modern HTML.
     *
     * @param  array<int, array<string, mixed>>|string|null  $blocks
     */
    public static function render(mixed $blocks): string
    {
        $sanitizedBlocks = self::sanitizeBlocks($blocks);

        if (empty($sanitizedBlocks)) {
            return '';
        }

        $html = '<div class="cms-page-blocks space-y-12">';

        foreach ($sanitizedBlocks as $block) {
            $type = $block['type'];
            $data = $block['data'];

            $html .= match ($type) {
                'hero' => self::renderHero($data),
                'rich_text' => self::renderRichText($data),
                'feature_cards' => self::renderFeatureCards($data),
                'stats_grid' => self::renderStatsGrid($data),
                'faq_accordion' => self::renderFaqAccordion($data),
                'cta_banner' => self::renderCtaBanner($data),
                'embedded_form' => self::renderEmbeddedForm($data),
                'property_listings_grid' => self::renderPropertyListingsGrid($data),
                default => '',
            };
        }

        $html .= '</div>';

        return $html;
    }

    public static function stripMaliciousTags(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Strip scripts, iframes, styles and objects including their contents
        $cleaned = preg_replace('/<(script|iframe|style|object|embed)\b[^>]*>(.*?)<\/\1>/is', '', $value);
        $cleaned = preg_replace('/<(script|iframe|style|object|embed)\b[^>]*\/?>/is', '', (string) $cleaned);

        return mb_trim(strip_tags((string) $cleaned));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function renderHero(array $data): string
    {
        $title = htmlspecialchars((string) ($data['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $subtitle = htmlspecialchars((string) ($data['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8');
        $ctaLabel = htmlspecialchars((string) ($data['cta_label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $ctaUrl = htmlspecialchars((string) ($data['cta_url'] ?? ''), ENT_QUOTES, 'UTF-8');

        $buttonHtml = ($ctaLabel && $ctaUrl)
            ? "<div class=\"mt-8\"><a href=\"{$ctaUrl}\" class=\"inline-flex items-center px-6 py-3 rounded-lg bg-primary text-white font-medium hover:bg-primary-dark transition\">{$ctaLabel}</a></div>"
            : '';

        return <<<HTML
        <section class="cms-block-hero py-16 px-6 lg:px-12 bg-gradient-to-r from-slate-900 to-indigo-950 text-white rounded-2xl shadow-xl my-6">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-4xl lg:text-5xl font-extrabold tracking-tight mb-4">{$title}</h1>
                <p class="text-lg lg:text-xl text-slate-300 max-w-2xl mx-auto leading-relaxed">{$subtitle}</p>
                {$buttonHtml}
            </div>
        </section>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function renderRichText(array $data): string
    {
        $content = (string) ($data['content'] ?? '');

        return <<<HTML
        <section class="cms-block-rich-text prose lg:prose-lg max-w-none px-4 py-4 dark:prose-invert">
            {$content}
        </section>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function renderFeatureCards(array $data): string
    {
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $cardsHtml = '';

        foreach ($items as $item) {
            $title = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $desc = htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8');

            $cardsHtml .= <<<HTML
            <div class="p-6 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition">
                <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold mb-4">✓</div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">{$title}</h3>
                <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">{$desc}</p>
            </div>
            HTML;
        }

        return <<<HTML
        <section class="cms-block-features py-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {$cardsHtml}
            </div>
        </section>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function renderStatsGrid(array $data): string
    {
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $statsHtml = '';

        foreach ($items as $item) {
            $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $value = htmlspecialchars((string) ($item['value'] ?? ''), ENT_QUOTES, 'UTF-8');
            $suffix = htmlspecialchars((string) ($item['suffix'] ?? ''), ENT_QUOTES, 'UTF-8');

            $statsHtml .= <<<HTML
            <div class="text-center p-6 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
                <div class="text-3xl lg:text-4xl font-extrabold text-primary mb-1">{$value}<span class="text-secondary text-2xl">{$suffix}</span></div>
                <div class="text-xs uppercase tracking-wider font-semibold text-slate-500 dark:text-slate-400">{$label}</div>
            </div>
            HTML;
        }

        return <<<HTML
        <section class="cms-block-stats py-6">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                {$statsHtml}
            </div>
        </section>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function renderFaqAccordion(array $data): string
    {
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $faqHtml = '';

        foreach ($items as $item) {
            $question = htmlspecialchars((string) ($item['question'] ?? ''), ENT_QUOTES, 'UTF-8');
            $answer = (string) ($item['answer'] ?? '');

            $faqHtml .= <<<HTML
            <details class="group p-5 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-xs mb-3">
                <summary class="flex justify-between items-center font-bold text-slate-900 dark:text-white cursor-pointer select-none">
                    <span>{$question}</span>
                    <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="mt-4 text-sm text-slate-600 dark:text-slate-300 leading-relaxed border-t border-slate-100 dark:border-slate-700/50 pt-3">
                    {$answer}
                </div>
            </details>
            HTML;
        }

        return <<<HTML
        <section class="cms-block-faq py-8 max-w-4xl mx-auto">
            <h2 class="text-2xl font-bold text-center text-slate-900 dark:text-white mb-6">Frequently Asked Questions</h2>
            <div class="space-y-3">
                {$faqHtml}
            </div>
        </section>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function renderCtaBanner(array $data): string
    {
        $headline = htmlspecialchars((string) ($data['headline'] ?? ''), ENT_QUOTES, 'UTF-8');
        $desc = htmlspecialchars((string) ($data['description'] ?? ''), ENT_QUOTES, 'UTF-8');
        $btnText = htmlspecialchars((string) ($data['button_text'] ?? 'Get Started'), ENT_QUOTES, 'UTF-8');
        $btnUrl = htmlspecialchars((string) ($data['button_url'] ?? '#'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <section class="cms-block-cta p-8 lg:p-12 bg-primary rounded-2xl text-white text-center shadow-lg my-8">
            <h2 class="text-3xl font-extrabold mb-3">{$headline}</h2>
            <p class="text-primary-foreground/90 max-w-xl mx-auto mb-6 text-sm lg:text-base">{$desc}</p>
            <a href="{$btnUrl}" class="inline-flex items-center px-6 py-3 rounded-lg bg-white text-primary font-bold hover:bg-slate-100 transition shadow">
                {$btnText}
            </a>
        </section>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function renderEmbeddedForm(array $data): string
    {
        $formId = (int) ($data['form_id'] ?? 0);
        $title = htmlspecialchars((string) ($data['title'] ?? 'Submit Request'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <section class="cms-block-form p-6 bg-slate-50 dark:bg-slate-800/80 rounded-xl border border-slate-200 dark:border-slate-700 my-6" data-form-id="{$formId}">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">{$title}</h3>
            <div class="text-sm text-slate-500">Interactive form loading container (Form #{$formId})</div>
        </section>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function renderPropertyListingsGrid(array $data): string
    {
        $title = htmlspecialchars((string) ($data['title'] ?? 'Available Properties'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <section class="cms-block-properties py-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{$title}</h2>
                <a href="/site/properties" class="text-sm font-semibold text-primary hover:underline">View All Properties &rarr;</a>
            </div>
            <div class="p-6 bg-slate-100 dark:bg-slate-800 rounded-xl text-center text-sm text-slate-600 dark:text-slate-300">
                Live verified properties are streamed dynamically from the Real Estate registry.
            </div>
        </section>
        HTML;
    }

    private static function sanitizeUrl(string $url): string
    {
        $url = mb_trim($url);

        if ($url === '' || preg_match('/^(https?:\/\/|\/)/i', $url)) {
            return $url;
        }

        return '#';
    }
}
