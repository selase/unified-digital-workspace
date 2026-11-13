<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PostImageUploadRequest extends FormRequest
{
    /** @return list<string> */
    public static function imageRules(): array
    {
        return ['bail', 'mimes:jpg,jpeg,png,webp', 'image', 'max:3072'];
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('cms.media.manage');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['file' => ['required', ...self::imageRules(), 'dimensions:max_width=4096,max_height=4096']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose an image to insert.',
            'file.mimes' => 'Upload a JPEG, PNG or WebP image.',
            'file.image' => 'Choose a valid image file.',
            'file.max' => 'The image must be 3 MB or smaller.',
            'file.dimensions' => 'The image must be no larger than 4,096 pixels on either side.',
        ];
    }
}
