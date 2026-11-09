<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('beneficiaries.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) app(TenantContext::class)->activeTenantId();

        return [
            'delivered_on' => ['required', 'date', 'before_or_equal:today'],
            'type' => ['required', Rule::in(['food', 'medical', 'education', 'cash', 'shelter', 'training', 'counselling', 'other'])],
            'description' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Choose the kind of service.',
        ];
    }
}
