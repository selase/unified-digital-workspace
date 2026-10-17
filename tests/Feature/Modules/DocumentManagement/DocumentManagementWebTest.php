<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\DocumentManagement\Models\Document;
use App\Modules\DocumentManagement\Models\DocumentVersion;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
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
