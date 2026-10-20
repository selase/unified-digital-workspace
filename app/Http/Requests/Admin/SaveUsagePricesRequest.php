<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enum\UsageMetric;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SaveUsagePricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-superadmin-dashboard') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'usage_currency' => ['nullable', 'in:USD,GHS'],
            'usage_prices' => ['nullable', 'array'],
            'usage_prices.*' => ['array:unit_price,unit_quantity'],
            'usage_prices.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'usage_prices.*.unit_quantity' => ['nullable', 'numeric', 'min:0.0001'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validatePrices($validator, $this->input('usage_prices', []));
        }];
    }

    public function validatePrices(Validator $validator, mixed $prices): void
    {
        if (! is_array($prices)) {
            return;
        }
        foreach ($prices as $metric => $data) {
            if (! UsageMetric::tryFrom((string) $metric)) {
                $validator->errors()->add('usage_prices', 'Choose a supported usage metric.');
            }
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['usage_currency.in' => 'Choose USD or GHS.', 'usage_prices.*.unit_price.min' => 'A unit price must be zero or positive.'];
    }
}
