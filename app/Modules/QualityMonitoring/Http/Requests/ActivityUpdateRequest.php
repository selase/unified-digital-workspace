<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ActivityUpdateRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'responsible_id' => ['sometimes', 'nullable', 'uuid'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'string'],
            'weight' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'sort_order' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
