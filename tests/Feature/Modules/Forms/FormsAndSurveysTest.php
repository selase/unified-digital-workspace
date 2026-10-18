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

    test('departmental form with null target department strictly rejects non-admin users', function (): void {
        [$admin, $tenant] = createFormsTestContext();

        $formNullDept = FormDefinition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Misconfigured Departmental Form',
            'slug' => 'misconfigured-departmental-form',
            'type' => 'general',
            'target_audience' => 'departmental',
            'target_department_id' => null, // NULL department!
            'status' => 'published',
            'created_by' => (string) $admin->uuid,
        ]);

        $regularUser = User::factory()->create();
        $regularUser->tenants()->syncWithoutDetaching([$tenant->id]);
        $regularUser->givePermissionTo('forms.submit');

        // Access must strictly be rejected with 403
        $this->actingAs($regularUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('forms.public.show', $formNullDept->slug))
            ->assertStatus(403);
    });

    test('results view tabulates aggregates, choice percentages, numeric stats, and CSV export streams correctly', function (): void {
        [$user, $tenant] = createFormsTestContext();

        $form = FormDefinition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Staff Engagement Survey 2026',
            'slug' => 'staff-engagement-survey-2026',
            'type' => 'survey',
            'target_audience' => 'public',
            'status' => 'published',
            'created_by' => (string) $user->uuid,
        ]);

        $fieldSatisfaction = FormField::create([
            'tenant_id' => $tenant->id,
            'form_definition_id' => $form->id,
            'label' => 'Overall Satisfaction',
            'field_name' => 'overall_satisfaction',
            'field_type' => 'select',
            'is_required' => true,
            'options_json' => ['High', 'Moderate', 'Low'],
            'sort_order' => 1,
        ]);

        $fieldRating = FormField::create([
            'tenant_id' => $tenant->id,
            'form_definition_id' => $form->id,
            'label' => 'Happiness Score (1-10)',
            'field_name' => 'happiness_score',
            'field_type' => 'number',
            'is_required' => true,
            'sort_order' => 2,
        ]);

        $fieldText = FormField::create([
            'tenant_id' => $tenant->id,
            'form_definition_id' => $form->id,
            'label' => 'Suggestions',
            'field_name' => 'suggestions',
            'field_type' => 'textarea',
            'is_required' => false,
            'sort_order' => 3,
        ]);

        // Seed 3 submissions
        FormSubmission::create([
            'tenant_id' => $tenant->id,
            'form_definition_id' => $form->id,
            'responses_json' => [
                'overall_satisfaction' => 'High',
                'happiness_score' => 9,
                'suggestions' => 'More hybrid remote flexibility.',
            ],
            'status' => 'submitted',
        ]);

        FormSubmission::create([
            'tenant_id' => $tenant->id,
            'form_definition_id' => $form->id,
            'responses_json' => [
                'overall_satisfaction' => 'High',
                'happiness_score' => 8,
                'suggestions' => 'Upgrade monitors in conference room.',
            ],
            'status' => 'submitted',
        ]);

        FormSubmission::create([
            'tenant_id' => $tenant->id,
            'form_definition_id' => $form->id,
            'responses_json' => [
                'overall_satisfaction' => 'Low',
                'happiness_score' => 4,
                'suggestions' => 'Improve cafeteria catering quality.',
            ],
            'status' => 'submitted',
        ]);

        // 1. Results View
        $resultsResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('forms.results', $form));

        $resultsResponse->assertOk()
            ->assertSee('Results &amp; Analytics', false)
            ->assertSee('Overall Satisfaction')
            ->assertSee('66.7%') // High: 2/3 = 66.7%
            ->assertSee('33.3%') // Low: 1/3 = 33.3%
            ->assertSee('Happiness Score (1-10)');

        // 2. CSV Export
        $exportResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('forms.export-csv', $form));

        $exportResponse->assertOk();
        expect($exportResponse->headers->get('content-type'))->toContain('text/csv');

        ob_start();
        $exportResponse->sendContent();
        $csvContent = ob_get_clean();

        expect($csvContent)->toContain('Submission UUID')
            ->and($csvContent)->toContain('Overall Satisfaction')
            ->and($csvContent)->toContain('Happiness Score (1-10)')
            ->and($csvContent)->toContain('More hybrid remote flexibility.');
    });

    test('submission detail view displays full responses and enforces cross-form isolation', function (): void {
        [$user, $tenant] = createFormsTestContext();

        $formA = FormDefinition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Form Alpha',
            'slug' => 'form-alpha',
            'type' => 'general',
            'target_audience' => 'public',
            'status' => 'published',
            'created_by' => (string) $user->uuid,
        ]);

        $formB = FormDefinition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Form Beta',
            'slug' => 'form-beta',
            'type' => 'general',
            'target_audience' => 'public',
            'status' => 'published',
            'created_by' => (string) $user->uuid,
        ]);

        FormField::create([
            'tenant_id' => $tenant->id,
            'form_definition_id' => $formA->id,
            'label' => 'Feedback Topic',
            'field_name' => 'topic',
            'field_type' => 'text',
        ]);

        $submissionA = FormSubmission::create([
            'tenant_id' => $tenant->id,
            'form_definition_id' => $formA->id,
            'responses_json' => ['topic' => 'Facilities Air Conditioning Maintenance'],
            'status' => 'submitted',
        ]);

        // 1. Authorized user can view submission details
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('forms.submissions.show', [$formA, $submissionA]))
            ->assertOk()
            ->assertSee('Facilities Air Conditioning Maintenance');

        // 2. Mismatched parent form ID returns 404 Not Found
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('forms.submissions.show', [$formB, $submissionA]))
            ->assertNotFound();
    });

    test('draft form lifecycle allows editing and deleting but strictly rejects editing and deleting published forms', function (): void {
        [$user, $tenant] = createFormsTestContext();

        // 1. Create a Draft Form
        $draftResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('forms.store'), [
                'title' => 'Quarterly Safety Audit Draft',
                'slug' => 'quarterly-safety-audit-draft',
                'type' => 'general',
                'target_audience' => 'internal_only',
                'status' => 'draft',
                'fields' => [
                    ['label' => 'Inspector Name', 'field_name' => 'inspector_name', 'field_type' => 'text', 'is_required' => true],
                ],
            ]);

        $draftResponse->assertRedirect();
        $form = FormDefinition::where('slug', 'quarterly-safety-audit-draft')->firstOrFail();
        expect($form->status)->toBe('draft');

        // 2. View Edit Screen for Draft Form -> 200 OK
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('forms.edit', $form))
            ->assertOk()
            ->assertSee('Quarterly Safety Audit Draft');

        // 3. Update Draft Form
        $updateResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('forms.update', $form), [
                'title' => 'Quarterly Safety Audit Revised Draft',
                'type' => 'general',
                'target_audience' => 'internal_only',
                'status' => 'draft',
                'fields' => [
                    ['label' => 'Lead Safety Inspector', 'field_name' => 'lead_inspector', 'field_type' => 'text', 'is_required' => true],
                    ['label' => 'Deficiencies Found', 'field_name' => 'deficiencies', 'field_type' => 'textarea', 'is_required' => false],
                ],
            ]);

        $updateResponse->assertRedirect();
        $form->refresh();
        expect($form->title)->toBe('Quarterly Safety Audit Revised Draft')
            ->and($form->fields)->toHaveCount(2);

        // 4. Publish the form
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('forms.publish', $form))
            ->assertRedirect();

        $form->refresh();
        expect($form->status)->toBe('published');

        // 5. Adversarial: Editing or deleting a published form MUST be rejected with 422
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('forms.edit', $form))
            ->assertStatus(422);

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('forms.update', $form), [
                'title' => 'Illegal Hack While Published',
                'type' => 'general',
                'target_audience' => 'internal_only',
                'fields' => [
                    ['label' => 'Test', 'field_name' => 'test', 'field_type' => 'text'],
                ],
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('forms.destroy', $form))
            ->assertStatus(422);

        // 6. Delete another pure draft form
        $pureDraft = FormDefinition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Unneeded Draft Form',
            'slug' => 'unneeded-draft-form',
            'type' => 'general',
            'status' => 'draft',
            'created_by' => (string) $user->uuid,
        ]);

        $deleteResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('forms.destroy', $pureDraft));

        $deleteResponse->assertRedirect(route('forms.index'));
        expect(FormDefinition::where('id', $pureDraft->id)->exists())->toBeFalse();
    });

    test('anonymous single-response poll anti-abuse blocks duplicate votes from same client fingerprint', function (): void {
        [$user, $tenant] = createFormsTestContext();

        $poll = FormDefinition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Public Townhall Polling',
            'slug' => 'public-townhall-polling',
            'type' => 'live_poll',
            'target_audience' => 'public',
            'allow_anonymous' => true,
            'single_response_per_user' => true,
            'status' => 'published',
            'created_by' => (string) $user->uuid,
        ]);

        FormField::create([
            'tenant_id' => $tenant->id,
            'form_definition_id' => $poll->id,
            'label' => 'Preferred Date',
            'field_name' => 'selected_option',
            'field_type' => 'radio',
            'is_required' => true,
            'options_json' => ['Saturday Morning', 'Sunday Afternoon'],
        ]);

        // 1. Anonymous guest casts first vote
        $response1 = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.50', 'HTTP_USER_AGENT' => 'Mozilla/5.0 TestBrowser'])
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('forms.public.submit', $poll->slug), [
                'selected_option' => 'Saturday Morning',
            ]);

        $response1->assertRedirect();
        expect(PollVote::where('form_definition_id', $poll->id)->count())->toBe(1);

        // 2. Same anonymous guest (matching IP and User-Agent) attempts second vote -> 422 Unprocessable
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.50', 'HTTP_USER_AGENT' => 'Mozilla/5.0 TestBrowser'])
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('forms.public.submit', $poll->slug), [
                'selected_option' => 'Sunday Afternoon',
            ])
            ->assertStatus(422);

        expect(PollVote::where('form_definition_id', $poll->id)->count())->toBe(1);

        // 3. Different client (different IP and fresh session) can vote successfully
        $this->flushSession();
        $response3 = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.99', 'HTTP_USER_AGENT' => 'AnotherBrowser/1.0'])
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('forms.public.submit', $poll->slug), [
                'selected_option' => 'Sunday Afternoon',
            ]);

        $response3->assertRedirect();
        expect(PollVote::where('form_definition_id', $poll->id)->count())->toBe(2);
    });

    test('bounded retries generate distinct slugs without infinite loops', function (): void {
        [$user, $tenant] = createFormsTestContext();

        // Create initial form
        FormDefinition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Annual Survey',
            'slug' => 'annual-survey',
            'type' => 'survey',
            'created_by' => (string) $user->uuid,
        ]);

        // Store second form with identical title and slug
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('forms.store'), [
                'title' => 'Annual Survey',
                'slug' => 'annual-survey',
                'type' => 'survey',
                'target_audience' => 'public',
                'fields' => [
                    ['label' => 'Question 1', 'field_name' => 'q1', 'field_type' => 'text'],
                ],
            ])
            ->assertRedirect();

        $form2 = FormDefinition::where('slug', 'annual-survey-1')->first();
        expect($form2)->not->toBeNull();
    });

    test('index supports search, type, status, and audience filtering with pagination', function (): void {
        [$user, $tenant] = createFormsTestContext();

        FormDefinition::create([
            'tenant_id' => $tenant->id,
            'title' => 'General Feedback Intake',
            'slug' => 'general-feedback-intake',
            'type' => 'general',
            'target_audience' => 'public',
            'status' => 'published',
            'created_by' => (string) $user->uuid,
        ]);

        FormDefinition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Internal HR Equipment Requisition',
            'slug' => 'internal-hr-equipment-requisition',
            'type' => 'hr_request',
            'target_audience' => 'internal_only',
            'status' => 'draft',
            'created_by' => (string) $user->uuid,
        ]);

        // Filter by type=hr_request
        $response = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('forms.index', ['type' => 'hr_request']));

        $response->assertOk()
            ->assertSee('Internal HR Equipment Requisition')
            ->assertDontSee('General Feedback Intake');

        // Filter by search='Feedback'
        $searchResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('forms.index', ['search' => 'Feedback']));

        $searchResponse->assertOk()
            ->assertSee('General Feedback Intake')
            ->assertDontSee('Internal HR Equipment Requisition');
    });
});
