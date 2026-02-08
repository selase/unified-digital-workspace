<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class KpiStoreRequest extends FormRequest
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
            'indicator_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'target_value' => ['nullable', 'numeric'],
            'baseline_value' => ['nullable', 'numeric'],
            'direction' => ['nullable', 'string'],
            'calculation' => ['nullable', 'array'],
            'frequency' => ['nullable', 'string', 'max:50'],
        ];
    }
}
