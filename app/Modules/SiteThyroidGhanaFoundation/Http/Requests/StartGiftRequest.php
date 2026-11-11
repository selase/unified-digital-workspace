<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StartGiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'campaign_id' => ['nullable', 'integer'],
            'website' => ['nullable', 'max:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Please tell us your name.',
            'email.required' => 'We need your email to send your receipt.',
            'email.email' => 'Enter a valid email address.',
            'amount.required' => 'Enter the amount you would like to give.',
            'amount.min' => 'The smallest online gift is GHS 1.',
            'website.max' => 'We could not accept this form.',
        ];
    }
}
