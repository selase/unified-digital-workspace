<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Requests;

use App\Modules\CmsCore\Models\Media;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MediaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Media $media */
        $media = $this->route('media');
        $tenantId = $this->tenantId();

        return [
            'disk' => ['sometimes', 'string', 'max:255'],
            'path' => ['sometimes', 'string'],
            'original_filename' => ['sometimes', 'string'],
            'filename' => ['sometimes', 'string'],
            'extension' => ['nullable', 'string', 'max:50'],
            'mime_type' => ['sometimes', 'string', 'max:255'],
            'size_bytes' => ['sometimes', 'integer', 'min:0'],
            'checksum_sha256' => ['nullable', 'string', 'max:255'],
            'width' => ['nullable', 'integer', 'min:0'],
            'height' => ['nullable', 'integer', 'min:0'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0'],
            'bitrate' => ['nullable', 'integer', 'min:0'],
            'fps' => ['nullable', 'numeric', 'min:0'],
            'dominant_color' => ['nullable', 'string', 'max:50'],
            'blurhash' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'alt_text' => ['nullable', 'string'],
            'caption' => ['nullable', 'string'],
            'title' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'uploaded_by' => ['sometimes', 'string'],
            'source' => ['nullable', 'string', 'max:50'],
            'is_public' => ['sometimes', 'boolean'],
            'post_ids' => ['nullable', 'array'],
            'post_ids.*' => [
                'integer',
                Rule::exists('posts', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }

    private function tenantId(): ?string
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant ? (string) $tenant->id : null;
    }
}
