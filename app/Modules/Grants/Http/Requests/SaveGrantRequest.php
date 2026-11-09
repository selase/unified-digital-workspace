<?php

declare(strict_types=1);

namespace App\Modules\Grants\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('grants.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) app(TenantContext::class)->activeTenantId();

        return [
            'funder_id' => ['required', Rule::exists('grt_funders', 'id')->where('tenant_id', $tenantId)],
            'title' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'gt:0'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['pipeline', 'awarded', 'active', 'closed', 'declined'])],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'funder_id.required' => 'Choose the funder.',
            'total_amount.gt' => 'The grant amount must be more than zero.',
            'ends_on.after_or_equal' => 'The grant must end on or after its start date.',
        ];
    }
}
