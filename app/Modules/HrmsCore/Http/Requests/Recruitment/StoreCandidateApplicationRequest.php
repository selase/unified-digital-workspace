<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Recruitment;

use App\Modules\HrmsCore\Models\Recruitment\Candidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCandidateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('hrms.applications.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $tenantId = (string) (session('active_tenant_id') ?? $user?->tenant_id);

        return [
            'candidate_id' => [
                'nullable',
                'integer',
                Rule::exists(Candidate::class, 'id')->where('tenant_id', $tenantId),
            ],
            'first_name' => ['required_without:candidate_id', 'nullable', 'string', 'max:100'],
            'last_name' => ['required_without:candidate_id', 'nullable', 'string', 'max:100'],
            'email' => ['required_without:candidate_id', 'nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'current_employer' => ['nullable', 'string', 'max:255'],
            'current_position' => ['nullable', 'string', 'max:255'],
            'expected_salary' => ['nullable', 'numeric', 'min:0'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'highest_qualification' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'proposed_start_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'candidate_id.exists' => 'The selected candidate does not exist in this organization.',
            'first_name.required_without' => 'Candidate first name is required when adding a new applicant.',
            'last_name.required_without' => 'Candidate last name is required when adding a new applicant.',
            'email.required_without' => 'Candidate email address is required when adding a new applicant.',
            'email.email' => 'Please enter a valid candidate email address.',
        ];
    }
}
