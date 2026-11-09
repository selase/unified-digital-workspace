<?php

declare(strict_types=1);

namespace App\Modules\Donations\Http\Requests;

use App\Modules\Donations\Models\DonPledge;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SavePledgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('donations.record');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) app(TenantContext::class)->activeTenantId();

        return [
            'donor_id' => ['required', Rule::exists('don_donors', 'id')->where('tenant_id', $tenantId)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'frequency' => ['required', Rule::in(array_keys(DonPledge::FREQUENCIES))],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:starts_on'],
            'campaign_id' => ['nullable', Rule::exists('don_campaigns', 'id')->where('tenant_id', $tenantId)],
            'fund_id' => ['nullable', Rule::exists('fin_funds', 'id')->where('tenant_id', $tenantId)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'donor_id.required' => 'Choose the donor.',
            'amount.gt' => 'The pledge must be more than zero.',
            'ends_on.after' => 'The pledge must end after it starts.',
        ];
    }
}
