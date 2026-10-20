<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Memos;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Memos\Models\Memo;
use App\Modules\Memos\Models\MemoMinute;
use App\Modules\Memos\Models\MemoRecipient;
use App\Services\ModuleManager;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

/**
 * @return array{0: User, 1: Tenant}
 */
function createMemoWebTestContext(): array
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

it('renders the memos hub and list pages with Metronic components', function (): void {
    [$user, $tenant] = createMemoWebTestContext();

    $responseHub = actingAs($user)->get('/memos/hub');
    $responseHub->assertOk()
        ->assertSee('Memos Hub')
        ->assertSee('Draft New Memo')
        ->assertSee('Circulation Snapshot');

    $responseList = actingAs($user)->get('/memos/list');
    $responseList->assertOk()
        ->assertSee('Memoranda Registry')
        ->assertSee('All Circulation Statuses');
});

it('allows authorized users to view the memo creation form', function (): void {
    [$user, $tenant] = createMemoWebTestContext();

    $recipient = User::factory()->create();
    $tenant->users()->attach($recipient->id);

    $response = actingAs($user)->get('/memos/create');
    $response->assertOk()
        ->assertSee('Draft Memorandum')
        ->assertSee('Subject')
        ->assertSee('Sign & Dispatch Immediately', false)
        ->assertSee('Save as Draft');
});

it('creates a new memo as draft via web form', function (): void {
    [$user, $tenant] = createMemoWebTestContext();

    $recipient = User::factory()->create();
    $tenant->users()->attach($recipient->id);

    $response = actingAs($user)->post('/memos', [
        'subject' => 'POLICY UPDATE ON HEALTH AUDITS 2026',
        'body' => 'All clinical audit protocols must adhere to ISO 15189 guidelines.',
        'action' => 'draft',
        'recipients' => [
            [
                'type' => 'user',
                'id' => (string) $recipient->id,
                'role' => 'to',
            ],
        ],
    ]);

    $memo = Memo::where('subject', 'POLICY UPDATE ON HEALTH AUDITS 2026')->first();
    expect($memo)->not->toBeNull();
    expect($memo->status)->toBe(Memo::STATUS_DRAFT);
    expect($memo->sender_id)->toBe((string) $user->uuid);

    $response->assertRedirect(route('memos.show', $memo));
});

it('displays an official institutional memorandum with letterhead and signature status', function (): void {
    [$user, $tenant] = createMemoWebTestContext();

    $memo = Memo::create([
        'subject' => 'EXECUTIVE DIRECTIVE ON EQUIPMENT ACQUISITION',
        'body' => 'Approval has been granted for procurement of high-resolution ultrasound units.',
        'sender_id' => $user->uuid,
        'status' => Memo::STATUS_DRAFT,
    ]);

    $response = actingAs($user)->get(route('memos.show', $memo));
    $response->assertOk()
        ->assertSee('Institutional Memorandum')
        ->assertSee('EXECUTIVE DIRECTIVE ON EQUIPMENT ACQUISITION')
        ->assertSee('Unsigned Draft')
        ->assertSee('Official Minute Sheet');
});

it('appends sequential minutes to the official minute sheet', function (): void {
    [$user, $tenant] = createMemoWebTestContext();

    $memo = Memo::create([
        'subject' => 'CAPA REVIEW MINUTES',
        'body' => 'Review of CAPA-2026-001 root cause analysis.',
        'sender_id' => $user->uuid,
        'status' => Memo::STATUS_SENT,
        'sent_at' => now(),
        'signed_at' => now(),
    ]);

    $response = actingAs($user)->post(route('memos.minute', $memo), [
        'body' => 'Dr. Selase: Forwarded to Quality Assurance for immediate verification.',
    ]);

    $response->assertRedirect(route('memos.show', $memo));

    $minute = MemoMinute::where('memo_id', $memo->id)->first();
    expect($minute)->not->toBeNull();
    expect($minute->body)->toContain('Forwarded to Quality Assurance');
    expect($minute->author_id)->toBe((string) $user->uuid);
});

it('allows the author to digitally sign and dispatch an unsigned draft', function (): void {
    [$user, $tenant] = createMemoWebTestContext();

    $memo = Memo::create([
        'subject' => 'ANNUAL LEAVE CALENDAR 2026',
        'body' => 'Please submit leave requests before Friday.',
        'sender_id' => $user->uuid,
        'status' => Memo::STATUS_DRAFT,
    ]);

    $response = actingAs($user)->post(route('memos.sign', $memo));
    $response->assertRedirect(route('memos.show', $memo));

    $memo->refresh();
    expect($memo->status)->toBe(Memo::STATUS_SENT);
    expect($memo->signed_at)->not->toBeNull();
    expect($memo->sent_at)->not->toBeNull();
});

it('allows an addressee to acknowledge receipt of a memo', function (): void {
    [$user, $tenant] = createMemoWebTestContext();

    $recipientUser = User::factory()->create();
    $tenant->users()->attach($recipientUser->id);
    $recipientUser->givePermissionTo(['memos.view', 'memos.acknowledge']);

    $memo = Memo::create([
        'subject' => 'MANDATORY FIRE SAFETY DRILL',
        'body' => 'All personnel must report to the assembly point at 10:00 AM.',
        'sender_id' => $user->uuid,
        'status' => Memo::STATUS_SENT,
        'sent_at' => now(),
        'signed_at' => now(),
    ]);

    MemoRecipient::create([
        'memo_id' => $memo->id,
        'tenant_id' => $tenant->id,
        'recipient_type' => 'user',
        'recipient_id' => (string) $recipientUser->id,
        'role' => 'to',
        'requires_ack' => true,
    ]);

    $response = actingAs($recipientUser)->post(route('memos.acknowledge', $memo));
    $response->assertRedirect(route('memos.show', $memo));

    $recipientRecord = MemoRecipient::where('memo_id', $memo->id)->where('recipient_id', (string) $recipientUser->id)->first();
    expect($recipientRecord->acknowledged_at)->not->toBeNull();
    expect($recipientRecord->acknowledged_by_id)->toBe((string) $recipientUser->uuid);

    $memo->refresh();
    expect($memo->status)->toBe(Memo::STATUS_ACKNOWLEDGED);
});

it('denies unauthorized access without memos permissions', function (): void {
    $unauthorizedUser = User::factory()->create();
    [$tenant] = setupMemoTenantConnection($unauthorizedUser);
    app(ModuleManager::class)->enableForTenant('memos', $tenant);

    actingAs($unauthorizedUser)->get('/memos/hub')->assertForbidden();
    actingAs($unauthorizedUser)->get('/memos/create')->assertForbidden();
    actingAs($unauthorizedUser)->post('/memos', [
        'subject' => 'HACK ATTEMPT',
        'body' => 'Should fail',
        'recipients' => [],
    ])->assertForbidden();
});

it('shows a memo whose recipients are stored as uuids, numeric ids and org units without crashing', function (): void {
    [$user, $tenant] = createMemoWebTestContext();

    $uuidRecipient = User::factory()->create(['first_name' => 'Abena', 'last_name' => 'Mensah']);
    $idRecipient = User::factory()->create(['first_name' => 'Kofi', 'last_name' => 'Boateng']);

    $memo = Memo::create([
        'subject' => 'QUARTERLY REVIEW MEETING',
        'body' => 'The quarterly review will hold on Friday.',
        'sender_id' => $user->uuid,
        'status' => Memo::STATUS_SENT,
        'sent_at' => now(),
        'signed_at' => now(),
    ]);

    foreach ([
        ['user', (string) $uuidRecipient->uuid, 'to'],
        ['user', (string) $idRecipient->id, 'cc'],
        ['unit', '9999', 'to'],
    ] as [$type, $recipientId, $role]) {
        MemoRecipient::create([
            'memo_id' => $memo->id,
            'tenant_id' => $tenant->id,
            'recipient_type' => $type,
            'recipient_id' => $recipientId,
            'role' => $role,
            'requires_ack' => false,
        ]);
    }

    actingAs($user)->get(route('memos.show', $memo))
        ->assertOk()
        ->assertSee('QUARTERLY REVIEW MEETING')
        ->assertSee('Abena')
        ->assertSee('Kofi');
});
