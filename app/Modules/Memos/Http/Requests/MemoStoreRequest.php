<?php

declare(strict_types=1);

namespace App\Modules\Memos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MemoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('memos.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = ['user', 'unit', 'department', 'directorate', 'tenant'];
        $roles = ['to', 'cc'];

        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*.type' => ['required', 'string', Rule::in($types)],
            'recipients.*.role' => ['nullable', 'string', Rule::in($roles)],
            'recipients.*.id' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, callable $fail): void {
                    $index = (int) explode('.', $attribute)[1];
                    $type = $this->input("recipients.{$index}.type");

                    if ($type !== 'tenant' && empty($value)) {
                        $fail('Recipient id is required.');
                    }
                },
            ],
        ];
    }
}
