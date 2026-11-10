<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateReferralRequest extends FormRequest
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
            'status' => ['required', 'string'],
            'outcome' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Choose a status.',
        ];
    }
}
