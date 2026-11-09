<?php

declare(strict_types=1);

namespace App\Modules\Volunteers\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LogHoursRequest extends FormRequest
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
            'volunteer_id' => ['required', Rule::exists('vol_volunteers', 'id')->where('tenant_id', $tenantId)],
            'opportunity_id' => ['nullable', Rule::exists('vol_opportunities', 'id')->where('tenant_id', $tenantId)],
            'worked_on' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'gt:0', 'max:24'],
            'activity' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'volunteer_id.required' => 'Choose the volunteer.',
            'hours.gt' => 'Hours must be more than zero.',
            'hours.max' => 'Hours cannot be more than 24 in a day.',
        ];
    }
}
