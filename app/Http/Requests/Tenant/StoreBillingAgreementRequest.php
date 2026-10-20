<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBillingAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin.tenants.update') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $plans = config('usage-pricing.hybrid.website_plans', []);
        $planSlug = $this->input('plan_slug');
        $includedEditors = is_string($planSlug) ? (int) data_get($plans, $planSlug.'.editors', 0) : 0;

        return [
            'plan_slug' => ['required', 'string', Rule::in(array_keys($plans))],
            'package_slugs' => ['sometimes', 'array', 'max:16'],
            'package_slugs.*' => ['required', 'string', 'distinct', Rule::in(array_keys(config('usage-pricing.hybrid.packages', [])))],
            'currency' => ['required', Rule::in([$this->route('tenant')->billingCurrency()])],
            'editor_seats' => ['required', 'integer', 'min:'.$includedEditors, 'max:100000'],
            'setup_required' => ['required', 'boolean'],
            'confirm_adoption' => ['required', 'accepted'],
            'effective_from' => ['bail', 'required', 'date_format:Y-m-d', function (string $attribute, mixed $value, Closure $fail): void {
                $date = CarbonImmutable::parse($value);
                if ($date->day !== 1 || $date->lt(today())) {
                    $fail('Choose the first day of a future month, or today when today is the first day of the month.');
                }
            }],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'plan_slug.in' => 'Select a configured website plan.',
            'package_slugs.*.in' => 'Select only configured module subscriptions.',
            'currency.in' => 'The agreement currency must match the tenant billing currency.',
            'editor_seats.min' => 'Editor seats must cover the editors included in the selected plan.',
            'confirm_adoption.accepted' => 'Explicitly confirm customer adoption before scheduling this agreement.',
            'confirm_adoption.required' => 'Explicitly confirm customer adoption before scheduling this agreement.',
            'setup_required.boolean' => 'Choose whether the one-time setup fee applies.',
        ];
    }
}
