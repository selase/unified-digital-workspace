<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ActivityStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('qm.workplans.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'responsible_id' => ['nullable', 'uuid'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
            'weight' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
