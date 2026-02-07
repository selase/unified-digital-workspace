<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\DocumentManagement\Models\Document;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;

/**
 * @return array{0: User, 1: Tenant}
 */
function createDocumentApiContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

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
        '--database' => 'landlord',
        '--path' => app_path('Modules/DocumentManagement/Database/Migrations'),
        '--realpath' => true,
    ]);

    return [$user, $tenant];
}

it('creates and lists documents with visibility', function (): void {
    [$user] = createDocumentApiContext();

    $createResponse = actingAs($user, 'sanctum')->postJson('/api/document-management/v1/documents', [
        'title' => 'Employee Policy',
        'description' => 'Policy doc',
        'visibility' => [
            'is_private' => true,
        ],
    ]);

    $createResponse->assertCreated();

    $list = actingAs($user, 'sanctum')->getJson('/api/document-management/v1/documents?shared_with_me=1');

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
        'owner_id' => $owner->id,
        'visibility' => [
            'users' => [$otherUser->id],
        ],
    ]);

    $response = actingAs($otherUser, 'sanctum')->getJson('/api/document-management/v1/documents?shared_with_me=1');

    $response->assertSuccessful()->assertJsonFragment([
        'id' => $doc->id,
    ]);
});

it('uploads versions and downloads', function (): void {
    Storage::fake('public');
    [$user] = createDocumentApiContext();

    $doc = Document::create([
        'title' => 'Versioned Doc',
        'slug' => 'versioned-doc',
        'owner_id' => $user->id,
    ]);

    $upload = actingAs($user, 'sanctum')->postJson("/api/document-management/v1/documents/{$doc->id}/versions", [
        'file' => Illuminate\Http\UploadedFile::fake()->create('doc1.pdf', 10, 'application/pdf'),
    ]);

    $upload->assertCreated();

    $download = actingAs($user, 'sanctum')->get("/api/document-management/v1/documents/{$doc->id}/download");

    $download->assertStatus(Response::HTTP_OK);
});

it('creates quizzes and attempts', function (): void {
    [$user] = createDocumentApiContext();

    $doc = Document::create([
        'title' => 'Quiz Doc',
        'slug' => 'quiz-doc',
        'owner_id' => $user->id,
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
            ['question' => 'Q1', 'answer' => 'A'],
        ],
    ]);

    $attempt->assertCreated();
});
