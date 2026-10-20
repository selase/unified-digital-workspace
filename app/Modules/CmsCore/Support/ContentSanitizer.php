<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Support;

use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Neutralises author-supplied markup before it reaches a public page.
 */
final class ContentSanitizer
{
    private static ?HtmlSanitizer $htmlSanitizer = null;

    /**
     * Strip scripts, event handlers and unsafe URL schemes from rich-text HTML.
     */
    public static function html(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return self::htmlSanitizer()->sanitize($html);
    }

    /**
     * Render bundled Markdown without raw HTML or unsafe links.
     *
     * Guides can exceed the rich-text sanitizer's default 20 KB limit.
     */
    public static function markdown(string $markdown): string
    {
        $sanitizer = new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowRelativeLinks()
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->withMaxInputLength(1_000_000)
        );

        return $sanitizer->sanitize(Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }

    /**
     * Make CSS safe to embed inside a <style> element.
     *
     * Removing every "<" prevents "</style>" from closing the element early;
     * the ">" child combinator is left intact.
     */
    public static function css(?string $css): string
    {
        if ($css === null || $css === '') {
            return '';
        }

        $css = str_replace('<', '', $css);

        return (string) preg_replace('/(javascript|vbscript)\s*:|expression\s*\(|-moz-binding|behavior\s*:/i', '', $css);
    }

    private static function htmlSanitizer(): HtmlSanitizer
    {
        return self::$htmlSanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->allowMediaSchemes(['http', 'https'])
                ->allowAttribute('class', '*')
                ->allowAttribute('target', 'a')
                ->allowAttribute('rel', 'a')
        );
    }
}
