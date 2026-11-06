<?php

declare(strict_types=1);

use App\Mail\Users\ResendAccountPassword;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\putJson;

function grantUserUpdatePermission(User $user): void
{
    Permission::query()->firstOrCreate(
        ['name' => 'core.users.update', 'guard_name' => 'web'],
        ['uuid' => (string) Str::uuid(), 'category' => 'core'],
    );

    $user->givePermissionTo('core.users.update');
}

test('an administrator can resend a new password to a user in their organisation', function () {
    Mail::fake();

    $admin = User::factory()->create();
    $tenant = setActiveTenantForTest($admin);
    $admin->update(['tenant_id' => $tenant->id]);
    grantUserUpdatePermission($admin);

    $member = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('old-password')]);
    $tenant->users()->attach($member->id);

    actingAs($admin);

    putJson("/user-management/users/{$member->uuid}/resend-password")
        ->assertSuccessful()
        ->assertJsonPath('status', 'success');

    Mail::assertQueued(ResendAccountPassword::class, function (ResendAccountPassword $mail) use ($member): bool {
        return $mail->hasTo($member->email)
            && $mail->data['email'] === $member->email
            && Hash::check($mail->data['password'], $member->fresh()->password);
    });
    expect(Hash::check('old-password', $member->fresh()->password))->toBeFalse();
});

test('a user without the update permission cannot reset someone else\'s password', function () {
    Mail::fake();

    $actor = User::factory()->create();
    $tenant = setActiveTenantForTest($actor);
    $actor->update(['tenant_id' => $tenant->id]);

    $member = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('old-password')]);
    $tenant->users()->attach($member->id);

    actingAs($actor);

    putJson("/user-management/users/{$member->uuid}/resend-password")->assertForbidden();

    Mail::assertNothingQueued();
    expect(Hash::check('old-password', $member->fresh()->password))->toBeTrue();
});

test('an organisation administrator cannot reset a password in another organisation', function () {
    Mail::fake();

    $admin = User::factory()->create();
    $tenant = setActiveTenantForTest($admin);
    $admin->update(['tenant_id' => $tenant->id]);
    grantUserUpdatePermission($admin);

    $otherTenant = Tenant::factory()->create(['slug' => 'other-'.uniqid(), 'isolation_mode' => 'shared']);
    $outsider = User::factory()->create(['tenant_id' => $otherTenant->id, 'password' => bcrypt('old-password')]);

    actingAs($admin);

    putJson("/user-management/users/{$outsider->uuid}/resend-password")->assertForbidden();

    Mail::assertNothingQueued();
    expect(Hash::check('old-password', $outsider->fresh()->password))->toBeTrue();
});

test('a superadmin can resend a password to a user in any organisation', function () {
    Mail::fake();

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']));

    $tenant = Tenant::factory()->create(['slug' => 'any-'.uniqid(), 'isolation_mode' => 'shared']);
    $member = User::factory()->create(['tenant_id' => $tenant->id]);

    actingAs($superAdmin);

    putJson("/user-management/users/{$member->uuid}/resend-password")->assertSuccessful();

    Mail::assertQueued(ResendAccountPassword::class, fn (ResendAccountPassword $mail): bool => $mail->hasTo($member->email));
});
