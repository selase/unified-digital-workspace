<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Forms\Models\FormDefinition;
use App\Modules\Forms\Models\FormField;
use App\Modules\Forms\Models\FormSubmission;
use App\Modules\Forms\Models\PollVote;
use App\Services\ModuleManager;
use Spatie\Permission\Models\Permission;

function createFormsTestContext(): array
{
    $user = User::factory()->create([
        'email' => 'forms-admin-'.uniqid().'@example.com',
    ]);
    $tenant = setActiveTenantForTest($user);

    foreach (['forms.view', 'forms.create', 'forms.manage', 'forms.submit', 'forms.review'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Illuminate\Support\Str::uuid(), 'category' => 'forms']);
    }

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo(['forms.view', 'forms.create', 'forms.manage', 'forms.submit', 'forms.review']);
    app(ModuleManager::class)->enableForTenant('forms', $tenant);

    return [$user, $tenant];
}

describe('Phase 24: General Form Builder, HR Forms, Surveys & Live Polls (TRK-FORMS-SURVEYS)', function (): void {

    test('creates and publishes a general intake form with public submission handling', function (): void {
        [$user, $tenant] = createFormsTestContext();

        // 1. Create and Publish Form Definition
        $response = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('forms.store'), [
                'title' => 'Client Land Registration Inquiry',
                'slug' => 'client-land-registration-inquiry',
                'description' => 'Official inquiry intake for verified land registration inquiries.',
                'type' => 'general',
                'target_audience' => 'public',
                'allow_anonymous' => true,
                'single_response_per_user' => false,
                'fields' => [
                    ['label' => 'Applicant Name', 'field_name' => 'applicant_name', 'field_type' => 'text', 'is_required' => true],
                    ['label' => 'Contact Number', 'field_name' => 'phone_number', 'field_type' => 'text', 'is_required' => true],
                    ['label' => 'Target Parcel Area', 'field_name' => 'parcel_area', 'field_type' => 'select', 'is_required' => true, 'options' => "East Legon Hills\nPrampram Eco-City\nAppolonia"],
                    ['label' => 'Specific Requirements', 'field_name' => 'requirements', 'field_type' => 'textarea', 'is_required' => false],
                ],
            ]);

        $response->assertRedirect();

        $form = FormDefinition::where('slug', 'client-land-registration-inquiry')->firstOrFail();
        expect($form->fields)->toHaveCount(4);

        // 2. Public / Guest Submission
        $submitResponse = $this->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('forms.public.submit', $form->slug), [
                'applicant_name' => 'Kwame Mensah',
                'phone_number' => '+233241234567',
                'parcel_area' => 'Prampram Eco-City',
                'requirements' => 'Seeking 2 contiguous commercial plots near main access road.',
            ]);

        $submitResponse->assertRedirect()
            ->assertSessionHas('status', 'success');

        $submission = FormSubmission::where('form_definition_id', $form->id)->firstOrFail();
        expect($submission->responses_json['applicant_name'])->toBe('Kwame Mensah')
            ->and($submission->responses_json['parcel_area'])->toBe('Prampram Eco-City')
            ->and($submission->status)->toBe('submitted');
    });

    test('enforces authentication on internal HR forms and executes approval review workflow', function (): void {
        [$user, $tenant] = createFormsTestContext();

        // Create internal HR equipment requisition form
        $form = FormDefinition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Surveyor Equipment Requisition Form',
            'slug' => 'surveyor-equipment-requisition',
            'description' => 'Internal employee requisition for GPS RTK rovers and optical levels.',
            'type' => 'hr_request',
            'target_audience' => 'internal_only',
            'allow_anonymous' => false,
            'single_response_per_user' => false,
            'status' => 'published',
            'created_by' => (string) $user->uuid,
        ]);

        FormField::create([
            'tenant_id' => $tenant->id,
            'form_definition_id' => $form->id,
            'label' => 'Equipment Needed',
            'field_name' => 'equipment_needed',
            'field_type' => 'text',
            'is_required' => true,
        ]);

        // 1. Unauthenticated submission to internal_only form MUST be rejected (403)
        $this->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('forms.public.submit', $form->slug), [
                'equipment_needed' => 'Trimble R12i GNSS Receiver',
            ])
            ->assertForbidden();

        // 2. Authenticated Staff Submission
        $staffUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $tenant->users()->attach($staffUser->id);
        $submitResponse = $this->actingAs($staffUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('forms.public.submit', $form->slug), [
                'equipment_needed' => 'Trimble R12i GNSS Receiver',
            ]);

        $submitResponse->assertRedirect();
        $submission = FormSubmission::where('form_definition_id', $form->id)->firstOrFail();
        expect($submission->user_id)->toBe((string) $staffUser->uuid)
            ->and($submission->status)->toBe('submitted');

        // 3. HR Admin Review & Approval
        $reviewResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('forms.submissions.review', [$form, $submission]), [
                'status' => 'approved',
                'reviewer_notes' => 'Allocated from Central Equipment Depot Vault. Return within 14 days.',
            ]);

        $reviewResponse->assertRedirect()
            ->assertSessionHas('status', 'success');

        $submission->refresh();
        expect($submission->status)->toBe('approved')
            ->and($submission->reviewer_notes)->toContain('Allocated from Central Equipment Depot Vault')
            ->and($submission->reviewed_by)->toBe((string) $user->uuid)
            ->and($submission->reviewed_at)->not->toBeNull();
    });

    test('manages live community polls and strictly rejects duplicate votes under single-response rule', function (): void {
        [$user, $tenant] = createFormsTestContext();

        $voterA = User::factory()->create(['tenant_id' => $tenant->id]);
        $tenant->users()->attach($voterA->id);

        $poll = FormDefinition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Community Amenities Priority Poll 2026',
            'slug' => 'community-amenities-priority-poll',
            'description' => 'Vote for the primary community amenity to build in Phase 2.',
            'type' => 'live_poll',
            'target_audience' => 'public',
            'allow_anonymous' => false,
            'single_response_per_user' => true,
            'status' => 'published',
            'created_by' => (string) $user->uuid,
        ]);

        FormField::create([
            'tenant_id' => $tenant->id,
            'form_definition_id' => $poll->id,
            'label' => 'Preferred Amenity',
            'field_name' => 'selected_option',
            'field_type' => 'radio',
            'is_required' => true,
            'options_json' => ['Solar Mini-Grid', 'Commercial Clubhouse', 'Fiber Optic Broadband Hub'],
        ]);

        // 1. First Vote: Solar Mini-Grid
        $voteResponse = $this->actingAs($voterA)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('forms.public.submit', $poll->slug), [
                'selected_option' => 'Solar Mini-Grid',
            ]);

        $voteResponse->assertRedirect()
            ->assertSessionHas('status', 'success');

        expect(PollVote::where('form_definition_id', $poll->id)->count())->toBe(1);

        $tally = $poll->getPollTally();
        expect($tally['Solar Mini-Grid']['count'])->toBe(1)
            ->and($tally['Solar Mini-Grid']['percentage'])->toBe(100.0);

        // 2. Second Vote Attempt by Same User MUST be rejected (422)
        $this->actingAs($voterA)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('forms.public.submit', $poll->slug), [
                'selected_option' => 'Commercial Clubhouse',
            ])
            ->assertStatus(422);

        // Assert vote count did NOT increase and still remains 1
        expect(PollVote::where('form_definition_id', $poll->id)->count())->toBe(1);
    });

    test('strictly rejects submissions to closed or expired forms', function (): void {
        [$user, $tenant] = createFormsTestContext();

        $closedForm = FormDefinition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Expired Vendor RFP Registration',
            'slug' => 'expired-vendor-rfp',
            'type' => 'general',
            'target_audience' => 'public',
            'allow_anonymous' => true,
            'status' => 'closed', // Closed!
            'created_by' => (string) $user->uuid,
        ]);

        $this->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('forms.public.submit', $closedForm->slug), [
                'notes' => 'Attempted late response',
            ])
            ->assertStatus(422);

        expect(FormSubmission::where('form_definition_id', $closedForm->id)->exists())->toBeFalse();
    });

    test('adversarial reject: departmental form strictly restricts access to assigned department members', function (): void {
        [$admin, $tenant] = createFormsTestContext();

        $financeDept = App\Modules\HrmsCore\Models\Organization\Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'Finance Department',
            'slug' => 'finance-'.uniqid(),
            'is_active' => true,
        ]);

        $otherDept = App\Modules\HrmsCore\Models\Organization\Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'Logistics Department',
            'slug' => 'logistics-'.uniqid(),
            'is_active' => true,
        ]);

        $form = FormDefinition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Finance Department Expense Reconciliation',
            'slug' => 'finance-expense-reconciliation',
            'type' => 'general',
            'target_audience' => 'departmental',
            'target_department_id' => $financeDept->id,
            'status' => 'published',
            'created_by' => (string) $admin->uuid,
        ]);

        FormField::create([
            'tenant_id' => $tenant->id,
            'form_definition_id' => $form->id,
            'label' => 'Voucher Number',
            'field_name' => 'voucher_number',
            'field_type' => 'text',
            'is_required' => true,
            'sort_order' => 1,
        ]);

        // 1. User without any department receives 403 Forbidden
        $userNoDept = User::factory()->create(['email' => 'nodept-'.uniqid().'@example.com']);
        $userNoDept->tenants()->syncWithoutDetaching([$tenant->id]);
        $userNoDept->givePermissionTo('forms.submit');

        $this->actingAs($userNoDept)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('forms.public.show', $form->slug))
            ->assertStatus(403);

        $this->actingAs($userNoDept)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('forms.public.submit', $form->slug), ['voucher_number' => 'V-1001'])
            ->assertStatus(403);

        // 2. User from different department (Logistics) receives 403 Forbidden
        $userWrongDept = User::factory()->create(['email' => 'wrongdept-'.uniqid().'@example.com']);
        $userWrongDept->tenants()->syncWithoutDetaching([$tenant->id]);
        $userWrongDept->givePermissionTo('forms.submit');

        $empWrong = App\Modules\HrmsCore\Models\Employees\Employee::create([
            'tenant_id' => $tenant->id,
            'user_id' => $userWrongDept->id,
            'first_name' => 'Wrong',
            'last_name' => 'Officer',
            'email' => $userWrongDept->email,
        ]);
        $empWrong->departments()->attach($otherDept->id);

        $this->actingAs($userWrongDept)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('forms.public.show', $form->slug))
            ->assertStatus(403);

        // 3. User with matching department (Finance) succeeds
        $userFinance = User::factory()->create(['email' => 'finance-'.uniqid().'@example.com']);
        $userFinance->tenants()->syncWithoutDetaching([$tenant->id]);
        $userFinance->givePermissionTo('forms.submit');

        $empFinance = App\Modules\HrmsCore\Models\Employees\Employee::create([
            'tenant_id' => $tenant->id,
            'user_id' => $userFinance->id,
            'first_name' => 'Finance',
            'last_name' => 'Officer',
            'email' => $userFinance->email,
        ]);
        $empFinance->departments()->attach($financeDept->id);

        $this->actingAs($userFinance)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('forms.public.show', $form->slug))
            ->assertStatus(200);

        // 4. Admin with forms.manage can access regardless of department
        $this->actingAs($admin)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('forms.public.show', $form->slug))
            ->assertStatus(200);
    });
});
