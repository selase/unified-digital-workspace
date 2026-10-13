<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Requests;

use App\Modules\CmsCore\Support\EnquiryType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class EnquiryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(EnquiryType::class)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-.\s]{6,40}$/'],
            'organisation' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            'form_token' => ['required', 'string'],
            'website' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please tell us your name.',
            'email.required' => 'Please enter your email address so we can reply.',
            'email.email' => 'That email address does not look right.',
            'phone.regex' => 'Please enter a valid phone number, or leave it blank.',
            'message.required' => 'Please write a short message.',
            'message.min' => 'Please write a little more (at least 10 characters).',
            'message.max' => 'Your message is too long. Please keep it under 4,000 characters.',
        ];
    }

    /**
     * Visitors without JavaScript are sent back to the page they came from,
     * with a flag the page turns into a message. The path is taken from the
     * Referer without its host, so the redirect can never leave this site.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            parent::failedValidation($validator);
        }

        $path = '/'.mb_ltrim((string) parse_url((string) $this->headers->get('referer'), PHP_URL_PATH), '/');

        throw new HttpResponseException(redirect()->to($path.'?enquiry=invalid#enquiry'));
    }
}
