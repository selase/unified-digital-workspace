<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OpenCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('beneficiaries.cases.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) app(TenantContext::class)->activeTenantId();

        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['general', 'health', 'protection', 'education', 'livelihood', 'housing'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Give the case a title.',
        ];
    }
}
