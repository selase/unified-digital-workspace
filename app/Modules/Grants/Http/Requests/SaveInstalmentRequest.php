<?php

declare(strict_types=1);

namespace App\Modules\Grants\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

final class SaveInstalmentRequest extends FormRequest
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
            'expected_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'The instalment must be more than zero.',
        ];
    }
}
