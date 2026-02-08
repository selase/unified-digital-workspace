<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DataSourceStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'method' => ['nullable', 'string', 'max:100'],
            'custodian' => ['nullable', 'string', 'max:100'],
            'quality_notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
