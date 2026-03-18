<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CmsSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('cms.settings.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_tagline' => ['nullable', 'string', 'max:500'],
            'primary_color' => ['nullable', 'string', 'max:50'],
            'secondary_color' => ['nullable', 'string', 'max:50'],
            'logo_media_id' => ['nullable', 'integer'],
            'favicon_media_id' => ['nullable', 'integer'],
            'header_menu_id' => ['nullable', 'integer'],
            'footer_menu_id' => ['nullable', 'integer'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'active_theme' => ['nullable', 'string', 'max:100'],
            'custom_css' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
