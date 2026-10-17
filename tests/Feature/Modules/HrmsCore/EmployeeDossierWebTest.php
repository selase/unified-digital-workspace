<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
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
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createDossierTestContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [
        'hrms.employees.view',
        'hrms.employees.create',
        'hrms.employees.update',
        'hrms.employees.delete',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'category' => 'hrms-core',
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $user->givePermissionTo($permissions);
    app(ModuleManager::class)->enableForTenant('hrms-core', $tenant);

    return [$user, $tenant];
}

test('employee show page displays dossier tabs and eager loads sub-records', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employee = Employee::factory()->forTenant($tenant->id)->create([
        'title' => null,
        'first_name' => 'Abena',
        'middle_name' => null,
        'last_name' => 'Osei',
    ]);

    BankDetails::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'bank_name' => 'Ecobank Ghana',
        'account_number' => '1441000123456',
    ]);

    NextOfKin::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'name' => 'Kofi Osei',
        'relationship' => 'Brother',
        'phone' => '+233244000111',
    ]);

    $response = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('hrms-core.employees.show', $employee));

    $response->assertOk()
        ->assertSee('Abena Osei')
        ->assertSee('Banking Details')
        ->assertSee('Family &amp; Next of Kin', false)
        ->assertSee('Emergency Contacts')
        ->assertSee('Education &amp; Qualifications', false)
        ->assertSee('Work Experience')
        ->assertSee('Employment Status')
        ->assertSee('Ecobank Ghana')
        ->assertSee('Kofi Osei');
});

test('can update bank details', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employee = Employee::factory()->forTenant($tenant->id)->create();

    $response = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('hrms-core.employees.dossier.bank-details.update', $employee), [
            'bank_name' => 'Standard Chartered Bank',
            'branch_name' => 'High Street',
            'account_number' => '010011223344',
            'account_type' => 'Current',
            'sort_code' => 'SCBLGHAC',
        ]);

    $response->assertRedirect(route('hrms-core.employees.show', [$employee, '#tab_banking']));

    $bank = BankDetails::where('employee_id', $employee->id)->first();
    expect($bank)->not->toBeNull()
        ->and($bank->bank_name)->toBe('Standard Chartered Bank')
        ->and($bank->account_number)->toBe('010011223344')
        ->and($bank->tenant_id)->toBe($tenant->id);
});

test('can update next of kin', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employee = Employee::factory()->forTenant($tenant->id)->create();

    $response = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('hrms-core.employees.dossier.next-of-kin.update', $employee), [
            'name' => 'Akosua Mensah',
            'relationship' => 'Spouse',
            'phone' => '+233501234567',
            'email' => 'akosua@example.com',
            'address' => 'East Legon, Accra',
        ]);

    $response->assertRedirect(route('hrms-core.employees.show', [$employee, '#tab_family']));

    $kin = NextOfKin::where('employee_id', $employee->id)->first();
    expect($kin)->not->toBeNull()
        ->and($kin->name)->toBe('Akosua Mensah')
        ->and($kin->relationship)->toBe('Spouse')
        ->and($kin->tenant_id)->toBe($tenant->id);
});

test('can update parent details', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employee = Employee::factory()->forTenant($tenant->id)->create();

    $response = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('hrms-core.employees.dossier.parents.update', $employee), [
            'father_name' => 'Kwabena Mensah',
            'father_occupation' => 'Architect',
            'father_alive' => true,
            'mother_name' => 'Ama Serwaa',
            'mother_occupation' => 'Educationist',
            'mother_alive' => true,
        ]);

    $response->assertRedirect(route('hrms-core.employees.show', [$employee, '#tab_family']));

    $parent = EmployeeParent::where('employee_id', $employee->id)->first();
    expect($parent)->not->toBeNull()
        ->and($parent->father_name)->toBe('Kwabena Mensah')
        ->and($parent->mother_name)->toBe('Ama Serwaa')
        ->and($parent->father_alive)->toBeTrue()
        ->and($parent->tenant_id)->toBe($tenant->id);
});

test('can add and remove children', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employee = Employee::factory()->forTenant($tenant->id)->create();

    $storeResponse = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('hrms-core.employees.dossier.children.store', $employee), [
            'name' => 'Yaw Mensah',
            'date_of_birth' => '2018-06-12',
            'gender' => 'male',
        ]);

    $storeResponse->assertRedirect(route('hrms-core.employees.show', [$employee, '#tab_family']));

    $child = Children::where('employee_id', $employee->id)->first();
    expect($child)->not->toBeNull()
        ->and($child->name)->toBe('Yaw Mensah')
        ->and($child->tenant_id)->toBe($tenant->id);

    $deleteResponse = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->delete(route('hrms-core.employees.dossier.children.destroy', [$employee, $child]));

    $deleteResponse->assertRedirect(route('hrms-core.employees.show', [$employee, '#tab_family']));
    expect(Children::where('id', $child->id)->exists())->toBeFalse();
});

test('can add emergency contact and toggles primary flag', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employee = Employee::factory()->forTenant($tenant->id)->create();

    // Add first contact as primary
    test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('hrms-core.employees.dossier.emergency-contacts.store', $employee), [
            'name' => 'First Emergency',
            'relationship' => 'Friend',
            'phone' => '+23320111222',
            'is_primary' => true,
        ]);

    $first = EmergencyContact::where('name', 'First Emergency')->first();
    expect($first->is_primary)->toBeTrue();

    // Add second contact also marked as primary -> unsets first
    test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('hrms-core.employees.dossier.emergency-contacts.store', $employee), [
            'name' => 'Second Emergency',
            'relationship' => 'Doctor',
            'phone' => '+23320333444',
            'is_primary' => true,
        ]);

    $first->refresh();
    $second = EmergencyContact::where('name', 'Second Emergency')->first();

    expect($first->is_primary)->toBeFalse()
        ->and($second->is_primary)->toBeTrue();

    // Delete contact
    test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->delete(route('hrms-core.employees.dossier.emergency-contacts.destroy', [$employee, $second]));

    expect(EmergencyContact::where('id', $second->id)->exists())->toBeFalse();
});

test('can manage education and professional qualifications', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employee = Employee::factory()->forTenant($tenant->id)->create();

    // Store education
    test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('hrms-core.employees.dossier.education.store', $employee), [
            'institution_name' => 'Kwame Nkrumah University of Science and Technology',
            'qualification' => 'BSc Electrical Engineering',
            'field_of_study' => 'Engineering',
            'start_date' => '2012-09-01',
            'end_date' => '2016-06-30',
            'grade' => 'First Class',
        ]);

    $edu = EducationalBackground::where('employee_id', $employee->id)->first();
    expect($edu)->not->toBeNull()
        ->and($edu->institution_name)->toBe('Kwame Nkrumah University of Science and Technology');

    // Store qualification
    test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('hrms-core.employees.dossier.qualifications.store', $employee), [
            'certification_name' => 'Project Management Professional (PMP)',
            'issuing_body' => 'Project Management Institute',
            'certificate_number' => 'PMP-987654321',
            'date_obtained' => '2020-01-15',
            'expiry_date' => '2027-01-15',
        ]);

    $qual = ProfessionalQualification::where('employee_id', $employee->id)->first();
    expect($qual)->not->toBeNull()
        ->and($qual->certification_name)->toBe('Project Management Professional (PMP)');

    // Delete both
    test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->delete(route('hrms-core.employees.dossier.education.destroy', [$employee, $edu]));
    expect(EducationalBackground::where('id', $edu->id)->exists())->toBeFalse();

    test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->delete(route('hrms-core.employees.dossier.qualifications.destroy', [$employee, $qual]));
    expect(ProfessionalQualification::where('id', $qual->id)->exists())->toBeFalse();
});

test('can manage previous work experience', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employee = Employee::factory()->forTenant($tenant->id)->create();

    test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('hrms-core.employees.dossier.work-experiences.store', $employee), [
            'company_name' => 'MTN Ghana',
            'position' => 'Network Engineer',
            'start_date' => '2017-01-01',
            'end_date' => '2021-12-31',
            'responsibilities' => 'Managed BTS infrastructure',
            'reason_for_leaving' => 'Career advancement',
        ]);

    $exp = PreviousWorkExperience::where('employee_id', $employee->id)->first();
    expect($exp)->not->toBeNull()
        ->and($exp->company_name)->toBe('MTN Ghana');

    test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->delete(route('hrms-core.employees.dossier.work-experiences.destroy', [$employee, $exp]));

    expect(PreviousWorkExperience::where('id', $exp->id)->exists())->toBeFalse();
});

test('can update employment status and syncs active flag', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employee = Employee::factory()->forTenant($tenant->id)->create(['is_active' => true]);

    // Transition to suspended
    test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('hrms-core.employees.dossier.employment-statuses.store', $employee), [
            'status' => EmploymentStatus::STATUS_SUSPENDED,
            'effective_date' => '2026-10-01',
            'reason' => 'Pending disciplinary hearing',
            'is_current' => true,
        ]);

    $employee->refresh();
    expect($employee->is_active)->toBeFalse();

    $status = EmploymentStatus::where('employee_id', $employee->id)->where('is_current', true)->first();
    expect($status->status)->toBe(EmploymentStatus::STATUS_SUSPENDED);

    // Transition back to active
    test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('hrms-core.employees.dossier.employment-statuses.store', $employee), [
            'status' => EmploymentStatus::STATUS_ACTIVE,
            'effective_date' => '2026-10-15',
            'reason' => 'Reinstated following exoneration',
            'is_current' => true,
        ]);

    $employee->refresh();
    expect($employee->is_active)->toBeTrue();
});

test('adversarial: cannot update dossier of an employee belonging to another tenant', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $otherTenant = Tenant::factory()->create();
    $foreignEmployee = Employee::factory()->forTenant($otherTenant->id)->create();

    $response = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('hrms-core.employees.dossier.bank-details.update', $foreignEmployee), [
            'bank_name' => 'Attacker Bank',
            'account_number' => '9999999999',
        ]);

    expect(in_array($response->status(), [403, 404], true))->toBeTrue();
});

test('adversarial: cannot delete a child record belonging to a different employee', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employeeA = Employee::factory()->forTenant($tenant->id)->create();
    $employeeB = Employee::factory()->forTenant($tenant->id)->create();

    $childB = Children::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employeeB->id,
        'name' => 'Employee B Child',
    ]);

    // Attempting to delete childB through employeeA's route
    $response = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->delete(route('hrms-core.employees.dossier.children.destroy', [$employeeA, $childB]));

    $response->assertStatus(404);
    expect(Children::where('id', $childB->id)->exists())->toBeTrue();
});

test('adversarial: user without update permission is rejected with 403', function (): void {
    [$user, $tenant] = createDossierTestContext();

    // Revoke update permission
    $user->revokePermissionTo('hrms.employees.update');

    $employee = Employee::factory()->forTenant($tenant->id)->create();

    $response = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('hrms-core.employees.dossier.bank-details.update', $employee), [
            'bank_name' => 'Unauthorized Bank',
            'account_number' => '1234567890',
        ]);

    $response->assertStatus(403);
});

test('can update emergency contact and demotes previous primary', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employee = Employee::factory()->forTenant($tenant->id)->create();

    $contact1 = EmergencyContact::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'name' => 'Original Contact',
        'phone' => '+233201112233',
        'is_primary' => true,
    ]);

    $contact2 = EmergencyContact::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'name' => 'Secondary Contact',
        'phone' => '+233209998877',
        'is_primary' => false,
    ]);

    $response = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('hrms-core.employees.dossier.emergency-contacts.update', [$employee, $contact2]), [
            'name' => 'Promoted Primary Contact',
            'relationship' => 'Spouse',
            'phone' => '+233209998877',
            'is_primary' => '1',
        ]);

    $response->assertRedirect(route('hrms-core.employees.show', [$employee, '#tab_emergency']))
        ->assertSessionHas('status', 'success');

    expect($contact2->fresh()->name)->toBe('Promoted Primary Contact')
        ->and($contact2->fresh()->is_primary)->toBeTrue()
        ->and($contact1->fresh()->is_primary)->toBeFalse();
});

test('can update education record', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employee = Employee::factory()->forTenant($tenant->id)->create();

    $edu = EducationalBackground::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'institution_name' => 'Old College',
        'qualification' => 'Diploma',
    ]);

    $response = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('hrms-core.employees.dossier.education.update', [$employee, $edu]), [
            'institution_name' => 'University of Cape Coast',
            'qualification' => 'BSc Computer Science',
            'field_of_study' => 'Information Technology',
            'grade' => 'First Class',
            'start_date' => '2016-09-01',
            'end_date' => '2020-06-30',
        ]);

    $response->assertRedirect(route('hrms-core.employees.show', [$employee, '#tab_education']))
        ->assertSessionHas('status', 'success');

    $fresh = $edu->fresh();
    expect($fresh->institution_name)->toBe('University of Cape Coast')
        ->and($fresh->qualification)->toBe('BSc Computer Science')
        ->and($fresh->grade)->toBe('First Class');
});

test('can update qualification record', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employee = Employee::factory()->forTenant($tenant->id)->create();

    $qual = ProfessionalQualification::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'certification_name' => 'PMP Prep',
        'issuing_body' => 'PMI',
    ]);

    $response = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('hrms-core.employees.dossier.qualifications.update', [$employee, $qual]), [
            'certification_name' => 'Project Management Professional (PMP)',
            'issuing_body' => 'Project Management Institute',
            'certificate_number' => 'PMP-887766',
            'date_obtained' => '2023-01-15',
            'expiry_date' => '2026-01-15',
        ]);

    $response->assertRedirect(route('hrms-core.employees.show', [$employee, '#tab_education']))
        ->assertSessionHas('status', 'success');

    $fresh = $qual->fresh();
    expect($fresh->certification_name)->toBe('Project Management Professional (PMP)')
        ->and($fresh->certificate_number)->toBe('PMP-887766');
});

test('can update previous work experience record', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employee = Employee::factory()->forTenant($tenant->id)->create();

    $exp = PreviousWorkExperience::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'company_name' => 'Old Startup',
        'position' => 'Junior Developer',
    ]);

    $response = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('hrms-core.employees.dossier.work-experiences.update', [$employee, $exp]), [
            'company_name' => 'Global Tech Ltd',
            'position' => 'Senior Systems Engineer',
            'start_date' => '2020-01-01',
            'end_date' => '2023-12-31',
            'responsibilities' => 'Led architecture team',
            'reason_for_leaving' => 'Career advancement',
        ]);

    $response->assertRedirect(route('hrms-core.employees.show', [$employee, '#tab_experience']))
        ->assertSessionHas('status', 'success');

    $fresh = $exp->fresh();
    expect($fresh->company_name)->toBe('Global Tech Ltd')
        ->and($fresh->position)->toBe('Senior Systems Engineer')
        ->and($fresh->responsibilities)->toBe('Led architecture team');
});

test('adversarial: cannot update emergency contact belonging to another employee (404)', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employeeA = Employee::factory()->forTenant($tenant->id)->create();
    $employeeB = Employee::factory()->forTenant($tenant->id)->create();

    $contactB = EmergencyContact::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employeeB->id,
        'name' => 'Target Contact',
        'phone' => '+233244111222',
    ]);

    $response = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('hrms-core.employees.dossier.emergency-contacts.update', [$employeeA, $contactB]), [
            'name' => 'Hijacked Name',
            'phone' => '+233244111222',
        ]);

    $response->assertStatus(404);
    expect($contactB->fresh()->name)->toBe('Target Contact');
});

test('adversarial: cannot update education belonging to another employee (404)', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employeeA = Employee::factory()->forTenant($tenant->id)->create();
    $employeeB = Employee::factory()->forTenant($tenant->id)->create();

    $eduB = EducationalBackground::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employeeB->id,
        'institution_name' => 'Target Uni',
        'qualification' => 'Target Degree',
    ]);

    $response = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('hrms-core.employees.dossier.education.update', [$employeeA, $eduB]), [
            'institution_name' => 'Hijacked Uni',
            'qualification' => 'Target Degree',
        ]);

    $response->assertStatus(404);
    expect($eduB->fresh()->institution_name)->toBe('Target Uni');
});

test('adversarial: cannot update qualification belonging to another employee (404)', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employeeA = Employee::factory()->forTenant($tenant->id)->create();
    $employeeB = Employee::factory()->forTenant($tenant->id)->create();

    $qualB = ProfessionalQualification::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employeeB->id,
        'certification_name' => 'Target Cert',
        'issuing_body' => 'Target Board',
    ]);

    $response = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('hrms-core.employees.dossier.qualifications.update', [$employeeA, $qualB]), [
            'certification_name' => 'Hijacked Cert',
            'issuing_body' => 'Target Board',
        ]);

    $response->assertStatus(404);
    expect($qualB->fresh()->certification_name)->toBe('Target Cert');
});

test('adversarial: cannot update work experience belonging to another employee (404)', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employeeA = Employee::factory()->forTenant($tenant->id)->create();
    $employeeB = Employee::factory()->forTenant($tenant->id)->create();

    $expB = PreviousWorkExperience::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employeeB->id,
        'company_name' => 'Target Corp',
        'position' => 'Target Role',
    ]);

    $response = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('hrms-core.employees.dossier.work-experiences.update', [$employeeA, $expB]), [
            'company_name' => 'Hijacked Corp',
            'position' => 'Target Role',
        ]);

    $response->assertStatus(404);
    expect($expB->fresh()->company_name)->toBe('Target Corp');
});

test('validation: form requests return custom error messages on invalid input', function (): void {
    [$user, $tenant] = createDossierTestContext();

    $employee = Employee::factory()->forTenant($tenant->id)->create();

    // 1. Bank details missing name and account
    $res1 = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('hrms-core.employees.dossier.bank-details.update', $employee), []);
    $res1->assertSessionHasErrors([
        'bank_name' => 'The bank name is required.',
        'account_number' => 'The bank account number is required.',
    ]);

    // 2. Education end date before start date
    $res2 = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('hrms-core.employees.dossier.education.store', $employee), [
            'institution_name' => 'Valid University',
            'qualification' => 'BSc',
            'start_date' => '2024-01-01',
            'end_date' => '2023-01-01',
        ]);
    $res2->assertSessionHasErrors([
        'end_date' => 'End date must be on or after the start date.',
    ]);
});
