<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SavePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-superadmin-dashboard') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('packages', 'slug')->ignore($this->route('package'))],
            'price' => 'required|numeric|min:0|max:99999999.99',
            'price_ghs' => 'nullable|numeric|min:0|max:99999999.99',
            'usage_currency' => 'nullable|in:USD,GHS',
            'interval' => 'required|in:month,year',
            'billing_model' => 'required|in:flat_rate,per_seat',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'markup_percentage' => 'nullable|numeric|min:0',
            'features' => 'nullable|array',
            'features.*.enabled' => 'nullable|string',
            'features.*.value' => 'nullable|string',
            'usage_prices' => 'nullable|array',
            'usage_prices.*.unit_price' => 'nullable|numeric|min:0',
            'usage_prices.*.unit_quantity' => 'nullable|numeric|min:0.0001',
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            (new SaveUsagePricesRequest)->validatePrices($validator, $this->input('usage_prices', []));
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['price_ghs.min' => 'The GHS price must be zero or positive. Leave it blank if not configured.', 'usage_currency.in' => 'Choose USD or GHS.'];
    }
}
