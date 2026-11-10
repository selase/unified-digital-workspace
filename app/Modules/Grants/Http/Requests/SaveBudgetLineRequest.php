<?php

declare(strict_types=1);

namespace App\Modules\Grants\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveBudgetLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('grants.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['account_id.required' => 'Choose an expense account.', 'amount.gt' => 'The budget must be more than zero.'];
    }
}
