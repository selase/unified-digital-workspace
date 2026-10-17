<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Appraisal;

use App\Modules\HrmsCore\Enums\AppraisalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransitionAppraisalStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AppraisalStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Please specify the target appraisal workflow status.',
            'status.enum' => 'The selected appraisal workflow status is not valid.',
        ];
    }
}
