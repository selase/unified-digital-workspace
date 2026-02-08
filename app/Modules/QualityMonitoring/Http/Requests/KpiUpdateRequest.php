<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class KpiUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('qm.kpis.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'indicator_id' => ['sometimes', 'nullable', 'integer'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'target_value' => ['sometimes', 'nullable', 'numeric'],
            'baseline_value' => ['sometimes', 'nullable', 'numeric'],
            'direction' => ['sometimes', 'nullable', 'string'],
            'calculation' => ['sometimes', 'nullable', 'array'],
            'frequency' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
