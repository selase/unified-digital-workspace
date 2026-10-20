<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Services\Tenancy\BillingAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignBillingAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin.tenants.update') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['level' => ['required', 'string', Rule::in([...BillingAccessService::LEVELS, 'unassigned'])]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['level.in' => 'Select a supported billing access level.', 'level.required' => 'Select a billing access level.'];
    }
}
