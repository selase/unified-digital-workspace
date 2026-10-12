<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Support;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Sanitizes rich-text HTML both when written and when read, so rows stored
 * before the cast existed are also protected when rendered.
 *
 * @implements CastsAttributes<string, string|null>
 */
final class SanitizedHtml implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): string
    {
        return ContentSanitizer::html(is_string($value) ? $value : null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return ContentSanitizer::html(is_string($value) ? $value : null);
    }
}
