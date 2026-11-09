<?php

declare(strict_types=1);

namespace App\Modules\Volunteers\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

final class SaveOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('volunteers.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) app(TenantContext::class)->activeTenantId();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'spots' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Give the opportunity a title.',
        ];
    }
}
