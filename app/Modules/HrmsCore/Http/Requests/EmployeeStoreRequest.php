<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests;

use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Center;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EmployeeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('hrms.employees.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'employee_staff_id' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique(Employee::class, 'employee_staff_id')->where('tenant_id', $tenantId),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:50'],
            'grade_id' => [
                'nullable',
                'integer',
                Rule::exists(Grade::class, 'id')->where('tenant_id', $tenantId),
            ],
            'center_id' => [
                'nullable',
                'integer',
                Rule::exists(Center::class, 'id')->where('tenant_id', $tenantId),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function tenantId(): ?string
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant ? (string) $tenant->id : null;
    }
}
