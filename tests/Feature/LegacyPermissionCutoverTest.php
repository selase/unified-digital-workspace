<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use App\Services\Auth\AbilityAliasService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Documents the steady state after the Phase 3.4 cutover migration runs:
 * the legacy "verb noun" permission rows are gone, only module.action.scope
 * rows remain, and the alias bridge in AuthServiceProvider fails open
 * instead of throwing when a caller still uses a legacy name.
 */
it('removes legacy rows but preserves the new-style twins', function (): void {
    // Seed both halves of an alias pair, mimicking the state right after
    // the Phase 3.2 mirror migration but before Phase 3.4 has run.
    $newName = 'core.users.read';
    $legacyName = AbilityAliasService::toLegacy($newName);

    $newId = DB::table('permissions')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'name' => $newName,
        'guard_name' => 'web',
        'category' => 'users',
    ]);

    $legacyId = DB::table('permissions')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'name' => $legacyName,
        'guard_name' => 'web',
        'category' => 'users',
    ]);

    // Re-run the cutover migration explicitly — the test DB starts with no
    // legacy rows, so the boot-time migration was a no-op.
    (require database_path('migrations/landlord/2026_05_16_101012_delete_legacy_permission_names.php'))->up();

    expect(DB::table('permissions')->where('id', $legacyId)->exists())->toBeFalse();
    expect(DB::table('permissions')->where('id', $newId)->exists())->toBeTrue();
});

it('returns false (does not throw) for a legacy ability after cutover', function (): void {
    // The alias bridge translates `core.users.read` -> `read user`. After
    // cutover the legacy row is gone, so hasPermissionTo throws
    // PermissionDoesNotExist. The defensive catch in AuthServiceProvider::boot
    // must swallow it and return null, leaving the gate to deny without
    // surfacing the exception.
    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create();

    expect($user->can('read user'))->toBeFalse();
    expect($user->can('core.users.read'))->toBeFalse();
});

it('resolves a new-style ability when the user has a direct grant', function (): void {
    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::query()->firstOrCreate(
        ['name' => 'core.users.read'],
        ['uuid' => (string) Str::uuid(), 'category' => 'users']
    );

    $user = User::factory()->create();
    $user->givePermissionTo('core.users.read');

    expect($user->can('core.users.read'))->toBeTrue();
});
