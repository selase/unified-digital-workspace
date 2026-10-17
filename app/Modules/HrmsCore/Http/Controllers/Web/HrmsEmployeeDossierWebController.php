<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HrmsCore\Http\Requests\Dossier\BankDetailsRequest;
use App\Modules\HrmsCore\Http\Requests\Dossier\ChildRequest;
use App\Modules\HrmsCore\Http\Requests\Dossier\EducationRequest;
use App\Modules\HrmsCore\Http\Requests\Dossier\EmergencyContactRequest;
use App\Modules\HrmsCore\Http\Requests\Dossier\EmploymentStatusRequest;
use App\Modules\HrmsCore\Http\Requests\Dossier\NextOfKinRequest;
use App\Modules\HrmsCore\Http\Requests\Dossier\ParentsRequest;
use App\Modules\HrmsCore\Http\Requests\Dossier\QualificationRequest;
use App\Modules\HrmsCore\Http\Requests\Dossier\WorkExperienceRequest;
use App\Modules\HrmsCore\Models\Employees\BankDetails;
use App\Modules\HrmsCore\Models\Employees\Children;
use App\Modules\HrmsCore\Models\Employees\EducationalBackground;
use App\Modules\HrmsCore\Models\Employees\EmergencyContact;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Employees\EmployeeParent;
use App\Modules\HrmsCore\Models\Employees\EmploymentStatus;
use App\Modules\HrmsCore\Models\Employees\NextOfKin;
use App\Modules\HrmsCore\Models\Employees\PreviousWorkExperience;
use App\Modules\HrmsCore\Models\Employees\ProfessionalQualification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class HrmsEmployeeDossierWebController extends Controller
{
    public function updateBankDetails(BankDetailsRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);

        $validated = $request->validated();

        BankDetails::updateOrCreate(
            ['employee_id' => $employee->id],
            array_merge($validated, ['tenant_id' => $employee->tenant_id])
        );

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_banking'])
            ->with('status', 'success')
            ->with('message', 'Bank details updated successfully.');
    }

    public function updateNextOfKin(NextOfKinRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);

        $validated = $request->validated();

        NextOfKin::updateOrCreate(
            ['employee_id' => $employee->id],
            array_merge($validated, ['tenant_id' => $employee->tenant_id])
        );

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_family'])
            ->with('status', 'success')
            ->with('message', 'Next of kin record updated successfully.');
    }

    public function updateParents(ParentsRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);

        $validated = $request->validated();
        $validated['father_alive'] = $request->boolean('father_alive');
        $validated['mother_alive'] = $request->boolean('mother_alive');

        EmployeeParent::updateOrCreate(
            ['employee_id' => $employee->id],
            array_merge($validated, ['tenant_id' => $employee->tenant_id])
        );

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_family'])
            ->with('status', 'success')
            ->with('message', 'Parent details updated successfully.');
    }

    public function storeChild(ChildRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);

        $employee->children()->create(array_merge($request->validated(), ['tenant_id' => $employee->tenant_id]));

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_family'])
            ->with('status', 'success')
            ->with('message', 'Child record added successfully.');
    }

    public function destroyChild(Request $request, Employee $employee, Children $child): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);
        abort_if((int) $child->employee_id !== (int) $employee->id, 404);

        $child->delete();

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_family'])
            ->with('status', 'success')
            ->with('message', 'Child record removed.');
    }

    public function storeEmergencyContact(EmergencyContactRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);

        $validated = $request->validated();
        $isPrimary = $request->boolean('is_primary');
        if ($isPrimary) {
            $employee->emergencyContacts()->update(['is_primary' => false]);
        }
        $validated['is_primary'] = $isPrimary;

        $employee->emergencyContacts()->create(array_merge($validated, ['tenant_id' => $employee->tenant_id]));

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_emergency'])
            ->with('status', 'success')
            ->with('message', 'Emergency contact added successfully.');
    }

    public function updateEmergencyContact(EmergencyContactRequest $request, Employee $employee, EmergencyContact $contact): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);
        abort_if((int) $contact->employee_id !== (int) $employee->id, 404);

        $validated = $request->validated();
        $isPrimary = $request->boolean('is_primary');
        if ($isPrimary) {
            $employee->emergencyContacts()->where('id', '!=', $contact->id)->update(['is_primary' => false]);
        }
        $validated['is_primary'] = $isPrimary;

        $contact->update($validated);

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_emergency'])
            ->with('status', 'success')
            ->with('message', 'Emergency contact updated successfully.');
    }

    public function destroyEmergencyContact(Request $request, Employee $employee, EmergencyContact $contact): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);
        abort_if((int) $contact->employee_id !== (int) $employee->id, 404);

        $contact->delete();

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_emergency'])
            ->with('status', 'success')
            ->with('message', 'Emergency contact removed.');
    }

    public function storeEducation(EducationRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);

        $employee->educationalBackgrounds()->create(array_merge($request->validated(), ['tenant_id' => $employee->tenant_id]));

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_education'])
            ->with('status', 'success')
            ->with('message', 'Educational record added successfully.');
    }

    public function updateEducation(EducationRequest $request, Employee $employee, EducationalBackground $education): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);
        abort_if((int) $education->employee_id !== (int) $employee->id, 404);

        $education->update($request->validated());

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_education'])
            ->with('status', 'success')
            ->with('message', 'Educational record updated successfully.');
    }

    public function destroyEducation(Request $request, Employee $employee, EducationalBackground $education): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);
        abort_if((int) $education->employee_id !== (int) $employee->id, 404);

        $education->delete();

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_education'])
            ->with('status', 'success')
            ->with('message', 'Educational record removed.');
    }

    public function storeQualification(QualificationRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);

        $employee->professionalQualifications()->create(array_merge($request->validated(), ['tenant_id' => $employee->tenant_id]));

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_education'])
            ->with('status', 'success')
            ->with('message', 'Professional qualification added successfully.');
    }

    public function updateQualification(QualificationRequest $request, Employee $employee, ProfessionalQualification $qualification): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);
        abort_if((int) $qualification->employee_id !== (int) $employee->id, 404);

        $qualification->update($request->validated());

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_education'])
            ->with('status', 'success')
            ->with('message', 'Professional qualification updated successfully.');
    }

    public function destroyQualification(Request $request, Employee $employee, ProfessionalQualification $qualification): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);
        abort_if((int) $qualification->employee_id !== (int) $employee->id, 404);

        $qualification->delete();

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_education'])
            ->with('status', 'success')
            ->with('message', 'Professional qualification removed.');
    }

    public function storeWorkExperience(WorkExperienceRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);

        $employee->previousWorkExperiences()->create(array_merge($request->validated(), ['tenant_id' => $employee->tenant_id]));

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_experience'])
            ->with('status', 'success')
            ->with('message', 'Previous work experience record added successfully.');
    }

    public function updateWorkExperience(WorkExperienceRequest $request, Employee $employee, PreviousWorkExperience $experience): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);
        abort_if((int) $experience->employee_id !== (int) $employee->id, 404);

        $experience->update($request->validated());

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_experience'])
            ->with('status', 'success')
            ->with('message', 'Previous work experience record updated successfully.');
    }

    public function destroyWorkExperience(Request $request, Employee $employee, PreviousWorkExperience $experience): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);
        abort_if((int) $experience->employee_id !== (int) $employee->id, 404);

        $experience->delete();

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_experience'])
            ->with('status', 'success')
            ->with('message', 'Previous work experience record removed.');
    }

    public function storeEmploymentStatus(EmploymentStatusRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);

        $validated = $request->validated();
        $isCurrent = $request->boolean('is_current', true);
        if ($isCurrent) {
            $employee->employmentStatuses()->update(['is_current' => false]);
            $employee->update(['is_active' => $validated['status'] === EmploymentStatus::STATUS_ACTIVE]);
        }
        $validated['is_current'] = $isCurrent;

        $employee->employmentStatuses()->create(array_merge($validated, ['tenant_id' => $employee->tenant_id]));

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_status'])
            ->with('status', 'success')
            ->with('message', 'Employment status record updated.');
    }

    public function destroyEmploymentStatus(Request $request, Employee $employee, EmploymentStatus $status): RedirectResponse
    {
        $this->authorizeEmployeeAction($request, $employee);
        abort_if((int) $status->employee_id !== (int) $employee->id, 404);

        $status->delete();

        return redirect()
            ->route('hrms-core.employees.show', [$employee, '#tab_status'])
            ->with('status', 'success')
            ->with('message', 'Employment status record removed.');
    }

    private function authorizeEmployeeAction(Request $request, Employee $employee): void
    {
        $user = $request->user();
        if (! $user || ! $user->can('hrms.employees.update')) {
            abort(403);
        }

        $activeTenantId = (string) (session('active_tenant_id') ?? $user->tenant_id);
        if ($activeTenantId !== '' && (string) $employee->tenant_id !== $activeTenantId) {
            abort(403, 'Cross-tenant access denied.');
        }
    }
}
