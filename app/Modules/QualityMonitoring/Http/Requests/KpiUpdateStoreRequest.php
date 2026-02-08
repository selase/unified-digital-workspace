<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class KpiUpdateStoreRequest extends FormRequest
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
            'value' => ['nullable', 'numeric'],
            'captured_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }
}
