<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MediaStoreRequest extends FormRequest
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
        $tenantId = $this->tenantId();

        return [
            'disk' => ['required', 'string', 'max:255'],
            'path' => ['required', 'string'],
            'original_filename' => ['required', 'string'],
            'filename' => ['required', 'string'],
            'extension' => ['nullable', 'string', 'max:50'],
            'mime_type' => ['required', 'string', 'max:255'],
            'size_bytes' => ['required', 'integer', 'min:0'],
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
            'uploaded_by' => ['required', 'string'],
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
