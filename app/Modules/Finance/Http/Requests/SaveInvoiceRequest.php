<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Support\Money;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('finance.invoices.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) app(TenantContext::class)->activeTenantId();

        return [

            'type' => ['required', Rule::in(['invoice', 'quote'])],
            'contact_id' => ['required', Rule::exists('fin_contacts', 'id')->where('tenant_id', $tenantId)],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.account_id' => ['nullable', Rule::exists('fin_accounts', 'id')->where('tenant_id', $tenantId)->where('type', 'income')],
            'lines.*.tax_id' => ['nullable', Rule::exists('fin_taxes', 'id')->where('tenant_id', $tenantId)],
            'lines.*.fund_id' => ['nullable', Rule::exists('fin_funds', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [

            'contact_id.required' => 'Choose a customer.',
            'lines.required' => 'Add at least one line.',
            'lines.*.description.required' => 'Every line needs a description.',
            'lines.*.quantity.gt' => 'Quantity must be more than zero.',
        ];
    }

    /**
     * Prices arrive as decimals (100.50) and are stored as minor units.
     *
     * @return list<array<string, mixed>>
     */
    public function lineItems(): array
    {
        return collect($this->validated('lines'))->map(fn (array $line): array => [
            'description' => $line['description'],
            'quantity' => $line['quantity'],
            'unit_price' => Money::toMinor($line['unit_price']),
            'account_id' => $line['account_id'] ?? null,
            'tax_id' => $line['tax_id'] ?? null,
            'fund_id' => $line['fund_id'] ?? null,
        ])->values()->all();
    }
}
