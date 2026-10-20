<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class SaveTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-superadmin-dashboard') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('country_code') && is_string($this->input('country_code'))) {
            $country = mb_strtoupper(trim($this->input('country_code')));
            $this->merge(['country_code' => $country === '' ? null : $country]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'priority' => ['required', 'integer', 'min:1'],
            'country_code' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            'is_compound' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'country_code.regex' => 'Use a two-letter country code such as GH, or leave blank for a global rule.',
            'country_code.string' => 'The country code must be text.',
            'rate.min' => 'The tax rate cannot be negative.',
            'rate.max' => 'The tax rate must be at most 999.99%.',
            'priority.min' => 'Tax priority must be at least 1.',
            'is_compound.boolean' => 'Choose whether the tax is compound.',
            'is_active.boolean' => 'Choose whether the tax rule is active.',
        ];
    }
}
