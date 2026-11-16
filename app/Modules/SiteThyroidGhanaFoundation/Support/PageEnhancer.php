<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Support;

use Dom\Element;
use Dom\HTMLDocument;

/** Presents existing page content more visually without changing what editors write: questions become collapsible, the key statistics become tiles. */
final class PageEnhancer
{
    public static function apply(string $slug, string $html): string
    {
        return match ($slug) {
            'faq' => self::faqAccordion($html),
            'the-challenge' => self::statTiles($html),
            default => $html,
        };
    }

    public static function faqAccordion(string $html): string
    {
        if (! str_contains($html, '<h3')) {
            return $html;
        }

        $document = HTMLDocument::createFromString('<body>'.$html.'</body>', LIBXML_NOERROR);
        $body = $document->body;
        $answer = null;

        foreach (iterator_to_array($body->childNodes) as $node) {
            $tag = $node instanceof Element ? mb_strtolower($node->localName) : null;

            if ($tag === 'h3') {
                $details = $document->createElement('details');
                $details->setAttribute('class', 'tgf-faq-item');
                $summary = $document->createElement('summary');
                $summary->textContent = mb_trim($node->textContent);
                $answer = $document->createElement('div');
                $answer->setAttribute('class', 'tgf-faq-answer');
                $details->appendChild($summary);
                $details->appendChild($answer);
                $body->insertBefore($details, $node);
                $body->removeChild($node);
            } elseif ($tag === 'h1' || $tag === 'h2') {
                $answer = null;
            } elseif ($answer !== null) {
                $answer->appendChild($node);
            }
        }

        return $body->innerHTML;
    }

    public static function statTiles(string $html): string
    {
        if (! str_contains($html, 'Key Statistics')) {
            return $html;
        }

        $document = HTMLDocument::createFromString('<body>'.$html.'</body>', LIBXML_NOERROR);

        foreach ($document->querySelectorAll('h2') as $heading) {
            $list = $heading->nextElementSibling;
            if (mb_trim($heading->textContent) !== 'Key Statistics' || ! $list instanceof Element || mb_strtolower($list->localName) !== 'ul') {
                continue;
            }

            $tiles = self::tilesFrom($document, $list);
            if ($tiles === null) {
                return $html;
            }

            $list->parentNode->replaceChild($tiles, $list);

            return $document->body->innerHTML;
        }

        return $html;
    }

    private static function tilesFrom(HTMLDocument $document, Element $list): ?Element
    {
        $wrapper = $document->createElement('div');
        $wrapper->setAttribute('class', 'tgf-stats');
        $items = array_values(array_filter(iterator_to_array($list->children), static fn (Element $child): bool => mb_strtolower($child->localName) === 'li'));
        if ($items === []) {
            return null;
        }

        foreach ($items as $item) {
            $figure = $item->querySelector('strong');
            if ($figure === null || mb_trim($figure->textContent) === '') {
                return null;
            }

            $value = mb_trim($figure->textContent);
            $label = mb_trim(str_replace($value, '', $item->textContent));
            $label = mb_trim((string) preg_replace(['/\s+/u', '/:\s*,/u', '/\s+([,.;:])/u'], [' ', ',', '$1'], $label), " \t\n,:;-");

            $tile = $document->createElement('div');
            $tile->setAttribute('class', 'tgf-stat');
            $strong = $document->createElement('strong');
            $strong->textContent = $value;
            $span = $document->createElement('span');
            $span->textContent = $label;
            $tile->appendChild($strong);
            $tile->appendChild($span);
            $wrapper->appendChild($tile);
        }

        return $wrapper;
    }
}
