<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('beneficiaries.cases.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'referred_to' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
            'case_id' => ['nullable', 'integer'],
            'follow_up_on' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'referred_to.required' => 'Say who the person is being referred to.',
        ];
    }
}
