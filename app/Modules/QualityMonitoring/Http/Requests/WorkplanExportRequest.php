<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WorkplanExportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('qm.workplans.view') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'format' => ['nullable', 'in:csv,xlsx'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:50'],
            'impact_level' => ['nullable', 'string', 'max:50'],
            'escalation_level' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
