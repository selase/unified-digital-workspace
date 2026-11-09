<?php

declare(strict_types=1);

namespace App\Modules\Grants\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveFunderRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['foundation', 'government', 'corporate', 'multilateral', 'individual'])],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter the funder\'s name.',
        ];
    }
}
