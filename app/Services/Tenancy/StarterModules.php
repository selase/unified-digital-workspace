<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

/**
 * Works out which selected module packages a website plan includes at no charge.
 *
 * A plan may include a number of "starter" packages from an eligible list, up to a combined
 * monthly value. When more qualify than fit, the combination worth the most to the customer wins.
 */
final class StarterModules
{
    /**
     * @param  array<string, mixed>  $card  The hybrid pricing card, from config or a saved agreement snapshot.
     * @param  list<string>  $packageSlugs  Module packages the customer selected.
     * @return list<string> Package slugs that are included in the plan price.
     */
    public static function waived(array $card, string $planSlug, array $packageSlugs, string $currency): array
    {
        $starter = $card['website_plans'][$planSlug]['starter'] ?? null;
        if ($starter === null) {
            return [];
        }

        $eligible = array_values(array_filter(
            array_unique($packageSlugs),
            fn (string $slug): bool => in_array($slug, $starter['packages'], true) && isset($card['packages'][$slug]),
        ));
        $cap = (int) round($starter['cap'][$currency] * 100);
        $best = [];
        $bestValue = 0;
        foreach (self::subsets($eligible, (int) $starter['picks']) as $subset) {
            $value = array_sum(array_map(fn (string $slug): int => (int) round($card['packages'][$slug][$currency] * 100), $subset));
            if ($value <= $cap && $value > $bestValue) {
                $best = $subset;
                $bestValue = $value;
            }
        }

        return $best;
    }

    /**
     * @param  list<string>  $items
     * @return list<list<string>>
     */
    private static function subsets(array $items, int $maxSize): array
    {
        $subsets = [[]];
        foreach ($items as $item) {
            foreach ($subsets as $subset) {
                if (count($subset) < $maxSize) {
                    $subsets[] = [...$subset, $item];
                }
            }
        }

        return $subsets;
    }
}
