<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

final class InvoiceCheckoutStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'regex:/^[A-Za-z0-9_.=-]{1,200}$/D'],
            'access_code' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{1,200}$/D'],
        ];
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return $this->query->all();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reference.required' => 'The checkout reference is missing. Open the payment link from your invoice.',
            'reference.string' => 'The checkout reference is invalid.',
            'reference.regex' => 'The checkout reference is invalid.',
            'access_code.string' => 'The checkout link is invalid.',
            'access_code.regex' => 'The checkout link is invalid.',
        ];
    }
}
