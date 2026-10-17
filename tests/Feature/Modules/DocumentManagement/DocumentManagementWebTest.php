<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\DocumentManagement\Models\Document;
use App\Modules\DocumentManagement\Models\DocumentAudit;
use App\Modules\DocumentManagement\Models\DocumentVersion;
use App\Notifications\InstitutionalWorkflowAlert;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createDocumentWebContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [
        'documents.view',
        'documents.create',
        'documents.update',
        'documents.delete',
        'documents.publish',
        'documents.review',
        'documents.approve',
        'documents.manage_quizzes',
        'documents.manage_versions',
        'documents.audit.view',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'category' => 'documents',
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $user->givePermissionTo($permissions);
    app(ModuleManager::class)->enableForTenant('document-management', $tenant);

    return [$user, $tenant];
}

it('renders document hub and library with metronic components', function (): void {
    [$user, $tenant] = createDocumentWebContext();

    $doc = Document::create([
        'tenant_id' => $tenant->id,
        'title' => 'Infection Prevention Protocol',
        'slug' => 'infection-prevention-protocol',
        'category' => 'Clinical Practice Guideline',
        'status' => 'published',
        'owner_id' => (string) $user->uuid,
        'visibility' => ['tenant_wide' => true],
        'published_at' => now(),
    ]);

    $response = test()->actingAs($user)->get(route('document-management.index'));
    $response->assertOk()
        ->assertSee('Document Management Hub')
        ->assertSee('Infection Prevention Protocol')
        ->assertSee('Browse Library');

    $libResponse = test()->actingAs($user)->get(route('document-management.documents.index'));
    $libResponse->assertOk()
        ->assertSee('Controlled Document Library')
        ->assertSee('Infection Prevention Protocol')
        ->assertSee('Clinical Practice Guideline');
});

it('denies access to document library without view permission', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);
    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);
    app(ModuleManager::class)->enableForTenant('document-management', $tenant);

    $response = test()->actingAs($user)->get(route('document-management.documents.index'));
    $response->assertForbidden();
});

it('renders document registration form for authorized staff', function (): void {
    [$user] = createDocumentWebContext();

    $response = test()->actingAs($user)->get(route('document-management.documents.create'));
    $response->assertOk()
        ->assertSee('Register Controlled Document')
        ->assertSee('Document Specifications')
        ->assertSee('Standard Operating Procedure (SOP)');
});

it('stores controlled document with initial file and creates version 1.0', function (): void {
    Storage::fake('public');
    [$user, $tenant] = createDocumentWebContext();

    $file = UploadedFile::fake()->create('sop_ipc_v1.pdf', 120, 'application/pdf');

    $response = test()->actingAs($user)->post(route('document-management.documents.store'), [
        'title' => 'Surgical Safety Checklist SOP',
        'category' => 'Standard Operating Procedure (SOP)',
        'description' => 'Mandatory pre-operative checklist procedures for operating theaters.',
        'status' => 'draft',
        'file' => $file,
        'notes' => 'Initial committee review draft',
    ]);

    $document = Document::query()->where('title', 'Surgical Safety Checklist SOP')->first();
    expect($document)->not->toBeNull();
    assert($document !== null);

    $response->assertRedirect(route('document-management.documents.show', $document));

    expect($document->status)->toBe('draft')
        ->and($document->category)->toBe('Standard Operating Procedure (SOP)')
        ->and($document->current_version_id)->not->toBeNull();

    $version = $document->currentVersion;
    expect($version)->not->toBeNull()
        ->and($version->version_number)->toBe(1)
        ->and($version->filename)->toBe('sop_ipc_v1.pdf')
        ->and($version->checksum_sha256)->not->toBeNull()
        ->and($version->notes)->toBe('Initial committee review draft');

    expect($document->audits()->count())->toBe(2); // create & version_uploaded
});

it('displays document show view with active master version and audit history', function (): void {
    Storage::fake('public');
    [$user, $tenant] = createDocumentWebContext();

    $document = Document::create([
        'tenant_id' => $tenant->id,
        'title' => 'Code Blue Resuscitation Protocol',
        'slug' => 'code-blue-resuscitation-protocol',
        'category' => 'Clinical Practice Guideline',
        'status' => 'published',
        'owner_id' => (string) $user->uuid,
        'visibility' => ['tenant_wide' => true],
        'published_at' => now(),
    ]);

    $version = DocumentVersion::create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'version_number' => 1,
        'disk' => 'public',
        'path' => 'documents/'.$document->id.'/code_blue_v1.pdf',
        'filename' => 'code_blue_v1.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 10240,
        'checksum_sha256' => hash('sha256', 'mock content'),
        'uploaded_by_id' => (string) $user->uuid,
        'notes' => 'Approved by Clinical Governance Committee',
    ]);

    $document->update(['current_version_id' => $version->id]);

    $response = test()->actingAs($user)->get(route('document-management.documents.show', $document));
    $response->assertOk()
        ->assertSee('Code Blue Resuscitation Protocol')
        ->assertSee('code_blue_v1.pdf')
        ->assertSee('Approved by Clinical Governance Committee')
        ->assertSee('Download Master (v1)');
});

it('allows checking in a new version and updates the active revision', function (): void {
    Storage::fake('public');
    [$user, $tenant] = createDocumentWebContext();

    $document = Document::create([
        'tenant_id' => $tenant->id,
        'title' => 'Waste Segregation SOP',
        'slug' => 'waste-segregation-sop',
        'status' => 'draft',
        'owner_id' => (string) $user->uuid,
        'visibility' => ['tenant_wide' => true],
    ]);

    $v1 = DocumentVersion::create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'version_number' => 1,
        'disk' => 'public',
        'path' => 'documents/'.$document->id.'/v1.pdf',
        'filename' => 'waste_v1.pdf',
        'size_bytes' => 5000,
        'uploaded_by_id' => (string) $user->uuid,
        'notes' => 'V1 draft',
    ]);
    $document->update(['current_version_id' => $v1->id]);

    $v2File = UploadedFile::fake()->create('waste_v2.pdf', 300, 'application/pdf');

    $response = test()->actingAs($user)->post(route('document-management.documents.versions.store', $document), [
        'file' => $v2File,
        'notes' => 'Updated biohazard waste disposal guidelines per EPA mandate',
    ]);

    $response->assertRedirect(route('document-management.documents.show', $document));

    $document->refresh();
    $latestVersion = $document->currentVersion;

    expect($latestVersion->version_number)->toBe(2)
        ->and($latestVersion->filename)->toBe('waste_v2.pdf')
        ->and($latestVersion->notes)->toBe('Updated biohazard waste disposal guidelines per EPA mandate');

    expect($document->versions()->count())->toBe(2);
});

it('allows publishing and archiving controlled documents', function (): void {
    [$user, $tenant] = createDocumentWebContext();

    $document = Document::create([
        'tenant_id' => $tenant->id,
        'title' => 'Staff Grievance Policy',
        'slug' => 'staff-grievance-policy',
        'status' => 'draft',
        'owner_id' => (string) $user->uuid,
        'visibility' => ['tenant_wide' => true],
    ]);

    // Publish
    $pubResponse = test()->actingAs($user)->post(route('document-management.documents.publish', $document));
    $pubResponse->assertRedirect(route('document-management.documents.show', $document));

    $document->refresh();
    expect($document->status)->toBe('published')
        ->and($document->published_at)->not->toBeNull();

    // Archive
    $archResponse = test()->actingAs($user)->post(route('document-management.documents.archive', $document));
    $archResponse->assertRedirect(route('document-management.documents.show', $document));

    $document->refresh();
    expect($document->status)->toBe('archived');
});

it('downloads master controlled document file securely', function (): void {
    Storage::fake('public');
    [$user, $tenant] = createDocumentWebContext();

    $content = 'Confidential Hospital Clinical Trial Protocol Content';
    $path = 'documents/1/protocol.pdf';
    Storage::disk('public')->put($path, $content);

    $document = Document::create([
        'tenant_id' => $tenant->id,
        'title' => 'Clinical Trial Protocol',
        'slug' => 'clinical-trial-protocol',
        'status' => 'published',
        'owner_id' => (string) $user->uuid,
        'visibility' => ['tenant_wide' => true],
    ]);

    $version = DocumentVersion::create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'version_number' => 1,
        'disk' => 'public',
        'path' => $path,
        'filename' => 'protocol.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => mb_strlen($content),
        'uploaded_by_id' => (string) $user->uuid,
    ]);
    $document->update(['current_version_id' => $version->id]);

    $response = test()->actingAs($user)->get(route('document-management.documents.download', ['document' => $document]));
    $response->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=protocol.pdf');

    expect($document->audits()->where('event', 'download')->exists())->toBeTrue();
});

it('supports full ISO 6-stage lifecycle transitions, comments, and superseding old versions', function (): void {
    [$user, $tenant] = createDocumentWebContext();

    $reviewer = User::factory()->create(['tenant_id' => $tenant->id]);
    $tenant->users()->attach($reviewer->id);

    $approver = User::factory()->create(['tenant_id' => $tenant->id]);
    $tenant->users()->attach($approver->id);

    $reviewer->givePermissionTo('documents.review');
    $approver->givePermissionTo('documents.approve');

    // 1. Create older policy that will later be superseded
    $legacyPolicy = Document::create([
        'tenant_id' => $tenant->id,
        'title' => 'Legacy Sanitation Standard 2021',
        'slug' => 'legacy-sanitation-standard-2021',
        'status' => Document::STATUS_PUBLISHED,
        'owner_id' => (string) $user->uuid,
        'published_at' => now()->subYear(),
    ]);

    // 2. Create new Draft Document superseding legacy policy
    $doc = Document::create([
        'tenant_id' => $tenant->id,
        'title' => 'Clinical Infection Prevention SOP',
        'slug' => 'clinical-infection-prevention-sop',
        'status' => Document::STATUS_DRAFT,
        'owner_id' => (string) $user->uuid,
        'reviewer_id' => (string) $reviewer->uuid,
        'approver_id' => (string) $approver->uuid,
        'supersedes_id' => $legacyPolicy->id,
        'category' => 'Standard Operating Procedure (SOP)',
    ]);

    // 3. Submit for Review
    $submitResponse = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('document-management.documents.submit-review', $doc), [
            'reviewer_id' => (string) $reviewer->uuid,
            'review_due_at' => now()->addDays(5)->toDateString(),
            'notes' => 'Submitted for clinical safety review',
        ]);
    $submitResponse->assertRedirect(route('document-management.documents.show', $doc));

    $doc->refresh();
    expect($doc->status)->toBe(Document::STATUS_IN_REVIEW)
        ->and($doc->isInReview())->toBeTrue();

    // 4. Reviewer Rejects with Feedback -> Under Revision
    $rejectResponse = test()->actingAs($reviewer)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('document-management.documents.reject', $doc), [
            'review_comments' => 'Missing decontamination cycle thresholds for autoclave machines.',
        ]);
    $rejectResponse->assertRedirect(route('document-management.documents.show', $doc));

    $doc->refresh();
    expect($doc->status)->toBe(Document::STATUS_UNDER_REVISION)
        ->and($doc->isUnderRevision())->toBeTrue()
        ->and($doc->review_comments)->toBe('Missing decontamination cycle thresholds for autoclave machines.');

    // 5. Re-submit for review
    test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('document-management.documents.submit-review', $doc));

    $doc->refresh();
    expect($doc->status)->toBe(Document::STATUS_IN_REVIEW);

    // 6. Approver Approves
    $approveResponse = test()->actingAs($approver)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('document-management.documents.approve', $doc), [
            'review_comments' => 'Autoclave thresholds verified. Approved for hospital-wide release.',
        ]);
    $approveResponse->assertRedirect(route('document-management.documents.show', $doc));

    $doc->refresh();
    expect($doc->status)->toBe(Document::STATUS_APPROVED)
        ->and($doc->isApproved())->toBeTrue()
        ->and($doc->approver_id)->toBe((string) $approver->uuid);

    // 7. Publish -> dispatches event and automatically marks legacyPolicy obsolete
    $pubResponse = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('document-management.documents.publish', $doc));
    $pubResponse->assertRedirect(route('document-management.documents.show', $doc));

    $doc->refresh();
    $legacyPolicy->refresh();

    expect($doc->status)->toBe(Document::STATUS_PUBLISHED)
        ->and($doc->isPublished())->toBeTrue()
        ->and($legacyPolicy->status)->toBe(Document::STATUS_OBSOLETE)
        ->and($legacyPolicy->superseded_by_id)->toBe($doc->id);

    // 8. Revise published document -> moves to under_revision
    $reviseResponse = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('document-management.documents.revise', $doc));
    $reviseResponse->assertRedirect(route('document-management.documents.show', $doc));

    $doc->refresh();
    expect($doc->status)->toBe(Document::STATUS_UNDER_REVISION);

    // 9. Mark obsolete
    $obsoleteResponse = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('document-management.documents.obsolete', $doc));
    $obsoleteResponse->assertRedirect(route('document-management.documents.show', $doc));

    $doc->refresh();
    expect($doc->status)->toBe(Document::STATUS_OBSOLETE);
});

it('supports document editing, updating, and soft deletion', function (): void {
    [$user, $tenant] = createDocumentWebContext();

    $doc = Document::create([
        'tenant_id' => $tenant->id,
        'title' => 'Operating Theatre Sanitation Standard',
        'slug' => 'ot-sanitation-standard',
        'status' => Document::STATUS_DRAFT,
        'owner_id' => (string) $user->uuid,
        'category' => 'Standard Operating Procedure (SOP)',
    ]);

    // Render Edit Form
    $editPage = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('document-management.documents.edit', $doc));
    $editPage->assertOk()
        ->assertSee('Edit: Operating Theatre Sanitation Standard')
        ->assertSee('ot-sanitation-standard', false);

    // Update document
    $updateResponse = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('document-management.documents.update', $doc), [
            'title' => 'Operating Theatre & Recovery Sanitation Standard',
            'category' => 'Administrative Manual',
            'description' => 'Updated scope including PACU and post-op recovery.',
            'status' => Document::STATUS_DRAFT,
        ]);
    $updateResponse->assertRedirect(route('document-management.documents.show', $doc));

    $doc->refresh();
    expect($doc->title)->toBe('Operating Theatre & Recovery Sanitation Standard')
        ->and($doc->category)->toBe('Administrative Manual')
        ->and($doc->description)->toBe('Updated scope including PACU and post-op recovery.');

    // Soft delete document
    $delResponse = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->delete(route('document-management.documents.destroy', $doc));
    $delResponse->assertRedirect(route('document-management.documents.index'));

    expect(Document::query()->find($doc->id))->toBeNull()
        ->and(Document::withTrashed()->find($doc->id))->not->toBeNull();
});

it('tracks user read acknowledgements and renders compliance reports', function (): void {
    [$user, $tenant] = createDocumentWebContext();
    $staffMember = User::factory()->create(['tenant_id' => $tenant->id]);
    $tenant->users()->attach($staffMember->id);
    $staffMember->givePermissionTo('documents.view');

    $doc = Document::create([
        'tenant_id' => $tenant->id,
        'title' => 'Controlled Blood Transfusion Standard',
        'slug' => 'blood-transfusion-standard',
        'status' => Document::STATUS_PUBLISHED,
        'owner_id' => (string) $user->uuid,
        'published_at' => now(),
    ]);

    // Initial check: not acknowledged
    expect($doc->isAcknowledgedBy((string) $staffMember->uuid))->toBeFalse();

    // Staff acknowledges reading the document
    $ackResponse = test()->actingAs($staffMember)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->from(route('document-management.documents.show', $doc))
        ->post(route('document-management.documents.acknowledge', $doc), [
            'notes' => 'Reviewed during orientation training',
        ]);
    $ackResponse->assertRedirect(route('document-management.documents.show', $doc));

    $doc->refresh();
    expect($doc->isAcknowledgedBy((string) $staffMember->uuid))->toBeTrue()
        ->and($doc->acknowledgements()->count())->toBe(1);

    // Compliance per-document view
    $compPage = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('document-management.documents.compliance', $doc));
    $compPage->assertOk()
        ->assertSee('Read Compliance: Controlled Blood Transfusion Standard')
        ->assertSee($staffMember->name)
        ->assertSee('Acknowledged');

    // Overview compliance report
    $overviewPage = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('document-management.compliance.index'));
    $overviewPage->assertOk()
        ->assertSee('ISO Document Compliance Overview')
        ->assertSee('Controlled Blood Transfusion Standard');
});

it('supports creating and taking comprehension quizzes in the web UI', function (): void {
    [$user, $tenant] = createDocumentWebContext();

    $doc = Document::create([
        'tenant_id' => $tenant->id,
        'title' => 'Radiation Safety Manual',
        'slug' => 'radiation-safety-manual',
        'status' => Document::STATUS_PUBLISHED,
        'owner_id' => (string) $user->uuid,
        'published_at' => now(),
    ]);

    // 1. Create Quiz view
    $createView = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('document-management.documents.quizzes.create', $doc));
    $createView->assertOk()
        ->assertSee('Create Quiz: Radiation Safety Manual');

    // 2. Store Quiz with Questions
    $storeQuizResponse = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('document-management.documents.quizzes.store', $doc), [
            'title' => 'Radiation Protection Staff Quiz',
            'description' => 'Mandatory test on badge dosimetry and shielding.',
            'pass_percentage' => 75,
            'questions' => [
                [
                    'body' => 'What is the minimum lead equivalence for aprons in fluoroscopy?',
                    'options' => ['0.15 mm Pb', '0.35 mm Pb', '0.50 mm Pb'],
                    'correct_option' => '1',
                    'points' => 2,
                ],
                [
                    'body' => 'How often must personal dosimeters be turned in for reading?',
                    'options' => ['Monthly', 'Annually', 'Never'],
                    'correct_option' => '0',
                    'points' => 2,
                ],
            ],
        ]);
    $storeQuizResponse->assertRedirect(route('document-management.documents.show', $doc));

    $quiz = $doc->quizzes()->first();
    expect($quiz)->not->toBeNull()
        ->and($quiz->title)->toBe('Radiation Protection Staff Quiz')
        ->and($quiz->questions()->count())->toBe(2);

    // 3. Render Quiz Screen
    $quizScreen = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('document-management.quizzes.show', $quiz));
    $quizScreen->assertOk()
        ->assertSee('Radiation Protection Staff Quiz')
        ->assertSee('What is the minimum lead equivalence');

    // 4. Attempt Quiz (Both correct -> 4/4 pts -> 100% -> Pass)
    $q1 = $quiz->questions[0];
    $q2 = $quiz->questions[1];

    $attemptResponse = test()->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('document-management.quizzes.attempt', $quiz), [
            'responses' => [
                $q1->id => '1',
                $q2->id => '0',
            ],
        ]);
    $attemptResponse->assertOk()
        ->assertSee('Assessment Passed Successfully')
        ->assertSee('4 / 4 pts')
        ->assertSee('100%');

    expect(App\Modules\DocumentManagement\Models\DocumentQuizAttempt::query()->where('quiz_id', $quiz->id)->count())->toBe(1);
});

it('enforces strict tenant isolation and denies unauthorized actions across document management', function (): void {
    // Tenant A
    [$userA, $tenantA] = createDocumentWebContext();

    // Tenant B
    $userB = User::factory()->create();
    $tenantB = setActiveTenantForTest($userB);
    Session::put('active_tenant_id', $tenantB->id);
    setPermissionsTeamId($tenantB->id);
    app(TenantContext::class)->setTenant($tenantB);
    app(ModuleManager::class)->enableForTenant('document-management', $tenantB);

    $permissions = [
        'documents.view',
        'documents.create',
        'documents.update',
        'documents.delete',
        'documents.publish',
        'documents.review',
        'documents.approve',
        'documents.manage_quizzes',
        'documents.manage_versions',
        'documents.audit.view',
    ];
    $userB->givePermissionTo($permissions);

    // Document belonging to Tenant B
    $foreignDoc = Document::create([
        'tenant_id' => $tenantB->id,
        'title' => 'Tenant B Confidential Clinical Standard',
        'slug' => 'tenant-b-clinical-standard',
        'status' => Document::STATUS_DRAFT,
        'owner_id' => (string) $userB->uuid,
        'visibility' => ['tenant_wide' => true],
    ]);

    // User A in Tenant A attempts to access Tenant B's document -> 404
    test()->actingAs($userA)
        ->withSession(['active_tenant_id' => $tenantA->id])
        ->get(route('document-management.documents.show', $foreignDoc))
        ->assertNotFound();

    test()->actingAs($userA)
        ->withSession(['active_tenant_id' => $tenantA->id])
        ->get(route('document-management.documents.edit', $foreignDoc))
        ->assertNotFound();

    test()->actingAs($userA)
        ->withSession(['active_tenant_id' => $tenantA->id])
        ->put(route('document-management.documents.update', $foreignDoc), ['title' => 'Tampered Title'])
        ->assertNotFound();

    test()->actingAs($userA)
        ->withSession(['active_tenant_id' => $tenantA->id])
        ->delete(route('document-management.documents.destroy', $foreignDoc))
        ->assertNotFound();

    test()->actingAs($userA)
        ->withSession(['active_tenant_id' => $tenantA->id])
        ->post(route('document-management.documents.submit-review', $foreignDoc))
        ->assertNotFound();

    test()->actingAs($userA)
        ->withSession(['active_tenant_id' => $tenantA->id])
        ->post(route('document-management.documents.approve', $foreignDoc))
        ->assertNotFound();

    test()->actingAs($userA)
        ->withSession(['active_tenant_id' => $tenantA->id])
        ->post(route('document-management.documents.publish', $foreignDoc))
        ->assertNotFound();

    test()->actingAs($userA)
        ->withSession(['active_tenant_id' => $tenantA->id])
        ->post(route('document-management.documents.acknowledge', $foreignDoc))
        ->assertNotFound();
});

it('dispatches review reminders and expiration alerts via documents:check-reviews command', function (): void {
    Notification::fake();
    [$user, $tenant] = createDocumentWebContext();

    $reviewer = User::factory()->create(['tenant_id' => $tenant->id]);
    $tenant->users()->attach($reviewer->id);

    // 1. In-review document due tomorrow
    $inReviewDoc = Document::create([
        'tenant_id' => $tenant->id,
        'title' => 'Emergency Triage Protocol',
        'slug' => 'emergency-triage-protocol',
        'status' => Document::STATUS_IN_REVIEW,
        'owner_id' => (string) $user->uuid,
        'reviewer_id' => (string) $reviewer->uuid,
        'review_due_at' => now()->addDay(),
    ]);

    // 2. Published document expiring in 5 days
    $expiringDoc = Document::create([
        'tenant_id' => $tenant->id,
        'title' => 'ICU Disinfection Policy',
        'slug' => 'icu-disinfection-policy',
        'status' => Document::STATUS_PUBLISHED,
        'owner_id' => (string) $user->uuid,
        'published_at' => now()->subYear(),
        'expires_at' => now()->addDays(5),
    ]);

    $exitCode = Artisan::call('documents:check-reviews', [
        '--tenant' => $tenant->id,
    ]);

    expect($exitCode)->toBe(0);

    Notification::assertSentTo($reviewer, InstitutionalWorkflowAlert::class, function ($notification) use ($inReviewDoc) {
        return str_contains($notification->title, 'ISO Review Due Soon') && str_contains($notification->title, $inReviewDoc->title);
    });

    Notification::assertSentTo($user, InstitutionalWorkflowAlert::class, function ($notification) use ($expiringDoc) {
        return str_contains($notification->title, 'Document Expiring Soon') && str_contains($notification->title, $expiringDoc->title);
    });

    expect(DocumentAudit::query()->where('document_id', $inReviewDoc->id)->where('event', 'review_reminder_sent')->exists())->toBeTrue()
        ->and(DocumentAudit::query()->where('document_id', $expiringDoc->id)->where('event', 'expiry_reminder_sent')->exists())->toBeTrue();
});
