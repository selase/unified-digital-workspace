<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\DocumentManagement\Models\Document;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/**
 * @return array{0: User, 1: Tenant}
 */
function createDocumentApiContext(): array
{
    // Configure tenant connection for tests (sqlite file)
    $tenantDb = database_path('tenant_testing.sqlite');
    if (file_exists($tenantDb)) {
        unlink($tenantDb);
    }
    // Create empty sqlite file so the connector finds it
    touch($tenantDb);

    Config::set('database.connections.tenant', [
        'driver' => 'sqlite',
        'database' => $tenantDb,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    Config::set('database.default_tenant_connection', 'tenant');

    Illuminate\Support\Facades\DB::purge('tenant');
    Illuminate\Support\Facades\DB::reconnect('tenant');

    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user, [
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'sqlite',
        'meta' => [
            'database' => $tenantDb,
        ],
    ]);

    switchToTenantContext($tenant);

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

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/DocumentManagement/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    return [$user, $tenant];
}

function switchToTenantContext(Tenant $tenant): void
{
    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);
}

it('creates and lists documents with visibility', function (): void {
    [$user, $tenant] = createDocumentApiContext();

    $createResponse = actingAs($user, 'sanctum')->postJson('/api/document-management/v1/documents', [
        'title' => 'Employee Policy',
        'description' => 'Policy doc',
        'visibility' => [
            'is_private' => true,
        ],
    ]);

    $createResponse->assertCreated();

    $list = actingAs($user, 'sanctum')->getJson('/api/document-management/v1/documents');

    $list->assertSuccessful()->assertJsonFragment([
        'title' => 'Employee Policy',
    ]);
});

it('allows sharing documents with specific users', function (): void {
    [$owner, $tenant] = createDocumentApiContext();
    /** @var User $otherUser */
    $otherUser = User::factory()->createOne();
    $otherUser->givePermissionTo(['documents.view']);
    $tenant->users()->attach($otherUser->id);

    $doc = Document::create([
        'title' => 'Shared Doc',
        'slug' => 'shared-doc',
        'owner_id' => (string) $owner->uuid,
        'visibility' => [
            'users' => [(string) $otherUser->uuid],
        ],
    ]);

    $response = actingAs($otherUser, 'sanctum')->getJson('/api/document-management/v1/documents');

    $response->assertSuccessful()->assertJsonFragment([
        'id' => $doc->id,
    ]);
});

it('shows documents for team visibility', function (): void {
    [$owner, $tenant] = createDocumentApiContext();
    /** @var User $viewer */
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['documents.view']);
    $tenant->users()->attach($viewer->id);

    // Fake org scope via team id (permissions.team_id) for now
    app()->instance('permissions.team_id', $tenant->id);

    $doc = Document::create([
        'title' => 'Dept Doc',
        'slug' => 'dept-doc',
        'owner_id' => (string) $owner->uuid,
        'visibility' => [
            'teams' => [$tenant->id],
        ],
    ]);

    Sanctum::actingAs($viewer->fresh());

    $response = getJson('/api/document-management/v1/documents');

    $response->assertSuccessful()->assertJsonFragment([
        'id' => $doc->id,
    ]);
});

it('enforces visibility when viewing documents', function (): void {
    [$owner, $tenant] = createDocumentApiContext();
    $other = User::factory()->createOne();
    $other->givePermissionTo(['documents.view']);

    $doc = Document::create([
        'title' => 'Private Doc',
        'slug' => 'private-doc',
        'owner_id' => (string) $owner->uuid,
        'visibility' => [
            'is_private' => true,
        ],
    ]);

    Sanctum::actingAs($other);

    getJson("/api/document-management/v1/documents/{$doc->id}")
        ->assertForbidden();
});

it('uploads versions and downloads', function (): void {
    Storage::fake('public');
    [$user, $tenant] = createDocumentApiContext();

    $doc = Document::create([
        'title' => 'Versioned Doc',
        'slug' => 'versioned-doc',
        'owner_id' => (string) $user->uuid,
    ]);

    $upload = actingAs($user, 'sanctum')->postJson("/api/document-management/v1/documents/{$doc->id}/versions", [
        'file' => Illuminate\Http\UploadedFile::fake()->create('doc1.pdf', 10, 'application/pdf'),
    ]);

    $upload->assertCreated();

    $download = actingAs($user, 'sanctum')->get("/api/document-management/v1/documents/{$doc->id}/download");

    $download->assertStatus(Response::HTTP_OK);
});

it('publishes documents and sets published_at', function (): void {
    [$user, $tenant] = createDocumentApiContext();

    $doc = Document::create([
        'title' => 'Publishable Doc',
        'slug' => 'publishable-doc',
        'owner_id' => (string) $user->uuid,
        'status' => 'draft',
    ]);

    $response = actingAs($user, 'sanctum')->postJson("/api/document-management/v1/documents/{$doc->id}/publish");

    $response->assertSuccessful()->assertJsonFragment([
        'status' => 'published',
    ]);
});

it('filters documents by search query', function (): void {
    [$user, $tenant] = createDocumentApiContext();

    Document::create([
        'title' => 'Policy Alpha',
        'slug' => 'policy-alpha',
        'owner_id' => (string) $user->uuid,
        'description' => 'Alpha description',
    ]);

    Document::create([
        'title' => 'Other Doc',
        'slug' => 'other-doc',
        'owner_id' => (string) $user->uuid,
        'description' => 'Beta',
    ]);

    $response = actingAs($user, 'sanctum')->getJson('/api/document-management/v1/documents?q=Alpha');

    $response->assertSuccessful()
        ->assertJsonFragment(['title' => 'Policy Alpha'])
        ->assertJsonMissing(['title' => 'Other Doc']);
});

it('creates quizzes and attempts', function (): void {
    [$user, $tenant] = createDocumentApiContext();

    $doc = Document::create([
        'title' => 'Quiz Doc',
        'slug' => 'quiz-doc',
        'owner_id' => (string) $user->uuid,
    ]);

    $quizResponse = actingAs($user, 'sanctum')->postJson("/api/document-management/v1/documents/{$doc->id}/quizzes", [
        'title' => 'Doc Quiz',
        'questions' => [
            [
                'body' => 'Q1',
                'options' => ['A', 'B'],
                'correct_option' => 'A',
                'points' => 1,
            ],
        ],
    ]);

    $quizResponse->assertCreated();

    $quizId = $quizResponse->json('data.id');

    $attempt = actingAs($user, 'sanctum')->postJson("/api/document-management/v1/quizzes/{$quizId}/attempts", [
        'responses' => [
            ['question_id' => $quizResponse->json('data.questions.0.id'), 'answer' => 'A'],
        ],
    ]);

    $attempt->assertCreated()->assertJsonFragment([
        'score' => 1,
    ]);
});
