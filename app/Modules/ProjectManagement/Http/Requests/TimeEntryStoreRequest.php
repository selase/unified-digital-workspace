<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TimeEntryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('projects.time.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'minutes' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
        ];
    }
}
