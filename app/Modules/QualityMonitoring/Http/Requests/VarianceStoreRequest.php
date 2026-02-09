<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VarianceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('qm.variances.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:255'],
            'impact_level' => ['nullable', 'string', 'max:50'],
            'narrative' => ['required', 'string'],
            'corrective_action' => ['nullable', 'string'],
            'revised_date' => ['nullable', 'date'],
            'kpi_id' => ['nullable', 'integer'],
        ];
    }
}
