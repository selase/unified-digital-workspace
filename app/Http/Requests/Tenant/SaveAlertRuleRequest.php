<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Services\Alerts\AlertEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveAlertRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.settings.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'event' => ['required', Rule::in(array_keys(AlertEvent::all()))],
            'name' => ['required', 'string', 'max:120'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(AlertEvent::channels())],
            'emails' => ['nullable', 'array', 'max:20'],
            'emails.*' => ['email:rfc', 'max:254'],
            'phones' => ['nullable', 'array', 'max:20'],
            'phones.*' => ['phone:GH,INTERNATIONAL'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'channels.required' => 'Choose at least one way to be alerted.',
            'emails.*.email' => 'Each extra email must be a valid address.',
            'phones.*.phone' => 'Each extra phone number must be a valid number, for example 0244123456.',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['emails', 'phones'] as $field) {
            $raw = $this->input($field);
            if (is_string($raw)) {
                $this->merge([$field => array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', $raw) ?: [])))]);
            }
        }
    }
}
