<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Directorate;
use App\Modules\HrmsCore\Models\Organization\Unit;
use App\Modules\Memos\Models\Memo;
use App\Modules\Memos\Models\MemoRecipient;
use App\Services\ModuleManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

/**
 * @return array{0: User, 1: Tenant}
 */
function createMemoApiContext(): array
{
    $user = User::factory()->create([
        'phone_no' => '+233200000000',
    ]);

    [$tenant] = setupMemoTenantConnection($user);

    $permissions = [
        'memos.view',
        'memos.create',
        'memos.update',
        'memos.delete',
        'memos.send',
        'memos.sign',
        'memos.acknowledge',
        'memos.minute',
        'memos.share',
        'memos.actions.manage',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'category' => 'memos',
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $user->givePermissionTo($permissions);

    app(ModuleManager::class)->enableForTenant('memos', $tenant);

    return [$user, $tenant];
}

it('creates and lists memos', function (): void {
    [$user, $tenant] = createMemoApiContext();

    $recipient = User::factory()->create();
    $tenant->users()->attach($recipient->id);
    $recipient->givePermissionTo(['memos.view']);

    $create = actingAs($user, 'sanctum')->postJson('/api/memos/v1/memos', [
        'subject' => 'Budget Review',
        'body' => 'Please review the quarterly budget.',
        'recipients' => [
            [
                'type' => 'user',
                'id' => (string) $recipient->id,
                'role' => 'to',
            ],
        ],
    ]);

    $create->assertCreated()->assertJsonFragment([
        'subject' => 'Budget Review',
        'status' => Memo::STATUS_DRAFT,
    ]);

    $list = actingAs($user, 'sanctum')->getJson('/api/memos/v1/memos');

    $list->assertSuccessful()->assertJsonFragment([
        'subject' => 'Budget Review',
    ]);
});

it('sends verification code and confirms memo send', function (): void {
    Storage::fake('tenant');

    [$user, $tenant] = createMemoApiContext();

    $recipient = User::factory()->create();
    $tenant->users()->attach($recipient->id);

    $create = actingAs($user, 'sanctum')->postJson('/api/memos/v1/memos', [
        'subject' => 'Policy Update',
        'body' => 'Updated policy details.',
        'recipients' => [
            [
                'type' => 'user',
                'id' => (string) $recipient->id,
                'role' => 'to',
            ],
        ],
    ]);

    $create->assertCreated();

    $memoUuid = $create->json('data.uuid');

    $signature = actingAs($user, 'sanctum')->post("/api/memos/v1/memos/{$memoUuid}/signature", [
        'signature' => UploadedFile::fake()->image('signature.png'),
    ]);

    $signature->assertSuccessful();

    config([
        'services.sms.default' => 'mnotify',
        'services.mnotify.key' => 'test-key',
        'services.mnotify.sender_id' => 'UDW',
    ]);

    Http::fake();

    $send = actingAs($user, 'sanctum')->postJson("/api/memos/v1/memos/{$memoUuid}/send-code");

    $send->assertSuccessful()->assertJson([
        'message' => 'Verification code sent.',
    ]);

    Http::assertSentCount(1);

    $memo = Memo::query()->where('uuid', $memoUuid)->firstOrFail();
    expect($memo->status)->toBe(Memo::STATUS_PENDING);

    $confirm = actingAs($user, 'sanctum')->postJson("/api/memos/v1/memos/{$memoUuid}/confirm-send", [
        'code' => '123456',
    ]);

    $confirm->assertSuccessful()->assertJsonFragment([
        'status' => Memo::STATUS_SENT,
    ]);
});

it('acknowledges required recipients and marks memo acknowledged', function (): void {
    [$sender, $tenant] = createMemoApiContext();

    $recipient = User::factory()->create();
    $recipient->givePermissionTo(['memos.view', 'memos.acknowledge']);
    $tenant->users()->attach($recipient->id);

    $ccRecipient = User::factory()->create();
    $ccRecipient->givePermissionTo(['memos.view']);
    $tenant->users()->attach($ccRecipient->id);

    $memo = Memo::create([
        'subject' => 'Action Required',
        'body' => 'Please acknowledge receipt.',
        'sender_id' => $sender->id,
        'status' => Memo::STATUS_SENT,
        'sent_at' => now(),
    ]);

    MemoRecipient::create([
        'memo_id' => $memo->id,
        'tenant_id' => $memo->tenant_id,
        'recipient_type' => 'user',
        'recipient_id' => (string) $recipient->id,
        'role' => MemoRecipient::ROLE_TO,
        'requires_ack' => true,
    ]);

    MemoRecipient::create([
        'memo_id' => $memo->id,
        'tenant_id' => $memo->tenant_id,
        'recipient_type' => 'user',
        'recipient_id' => (string) $ccRecipient->id,
        'role' => MemoRecipient::ROLE_CC,
        'requires_ack' => false,
    ]);

    $acknowledge = actingAs($recipient, 'sanctum')->postJson("/api/memos/v1/memos/{$memo->uuid}/acknowledge");

    $acknowledge->assertSuccessful()->assertJsonFragment([
        'status' => Memo::STATUS_ACKNOWLEDGED,
    ]);

    $memo->refresh();

    expect($memo->status)->toBe(Memo::STATUS_ACKNOWLEDGED);
});

it('returns recipient summaries for hrms recipients when tables exist', function (): void {
    [$user] = createMemoApiContext();

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/HrmsCore/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    $unit = Unit::create([
        'name' => 'Operations Unit',
        'is_active' => true,
    ]);

    $department = Department::create([
        'name' => 'Finance Department',
        'is_active' => true,
    ]);

    $directorate = Directorate::create([
        'name' => 'Strategy Directorate',
        'is_active' => true,
    ]);

    $create = actingAs($user, 'sanctum')->postJson('/api/memos/v1/memos', [
        'subject' => 'Org Update',
        'body' => 'Please review the organization update.',
        'recipients' => [
            [
                'type' => MemoRecipient::TYPE_UNIT,
                'id' => (string) $unit->id,
                'role' => MemoRecipient::ROLE_TO,
            ],
            [
                'type' => MemoRecipient::TYPE_DEPARTMENT,
                'id' => (string) $department->id,
                'role' => MemoRecipient::ROLE_CC,
            ],
            [
                'type' => MemoRecipient::TYPE_DIRECTORATE,
                'id' => (string) $directorate->id,
                'role' => MemoRecipient::ROLE_CC,
            ],
        ],
    ]);

    $create->assertCreated()
        ->assertJsonFragment([
            'recipient_type' => MemoRecipient::TYPE_UNIT,
            'recipient' => [
                'type' => MemoRecipient::TYPE_UNIT,
                'id' => $unit->id,
                'name' => $unit->name,
            ],
        ])
        ->assertJsonFragment([
            'recipient_type' => MemoRecipient::TYPE_DEPARTMENT,
            'recipient' => [
                'type' => MemoRecipient::TYPE_DEPARTMENT,
                'id' => $department->id,
                'name' => $department->name,
            ],
        ])
        ->assertJsonFragment([
            'recipient_type' => MemoRecipient::TYPE_DIRECTORATE,
            'recipient' => [
                'type' => MemoRecipient::TYPE_DIRECTORATE,
                'id' => $directorate->id,
                'name' => $directorate->name,
            ],
        ]);
});
