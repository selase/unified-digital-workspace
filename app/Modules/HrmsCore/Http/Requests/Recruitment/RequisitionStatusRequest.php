<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Recruitment;

use App\Modules\HrmsCore\Enums\RequisitionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RequisitionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('hrms.jobs.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(array_map(static fn (RequisitionStatus $case): string => $case->value, RequisitionStatus::cases())),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Target requisition status is required.',
            'status.in' => 'The selected requisition status is invalid.',
        ];
    }
}
