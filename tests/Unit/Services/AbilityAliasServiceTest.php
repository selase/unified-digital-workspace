<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use App\Services\Auth\AbilityAliasService;
use Spatie\Permission\PermissionRegistrar;

/**
 * The alias service maps module.action.scope names to legacy permission
 * names. Tests both the static map plus the Gate::before integration
 * that lets new-style abilities resolve via legacy permission grants.
 */
test('every new-style name maps back to a legacy name', function (): void {
    foreach (AbilityAliasService::all() as $new => $legacy) {
        expect($new)->toContain('.');
        expect($legacy)->not->toContain('.');
        expect(AbilityAliasService::toLegacy($new))->toBe($legacy);
        expect(AbilityAliasService::toNew($legacy))->toBe($new);
    }
});

test('unknown ability returns null from toLegacy', function (): void {
    expect(AbilityAliasService::toLegacy('cms.posts.view'))->toBeNull();
    expect(AbilityAliasService::toLegacy('nonexistent.thing.do'))->toBeNull();
});

test('isNewStyle returns true for dotted names only', function (): void {
    expect(AbilityAliasService::isNewStyle('core.users.read'))->toBeTrue();
    expect(AbilityAliasService::isNewStyle('read user'))->toBeFalse();
    expect(AbilityAliasService::isNewStyle('access-superadmin-dashboard'))->toBeFalse();
});

test('user with legacy permission resolves new-style ability via Gate', function (): void {
    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::query()->firstOrCreate(
        ['name' => 'read user'],
        ['uuid' => (string) Illuminate\Support\Str::uuid(), 'category' => 'user']
    );

    $user = User::factory()->create();
    $user->givePermissionTo('read user');

    expect($user->can('core.users.read'))->toBeTrue();
    expect($user->can('read user'))->toBeTrue();
});

test('user without legacy permission fails both names', function (): void {
    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::query()->firstOrCreate(
        ['name' => 'read user'],
        ['uuid' => (string) Illuminate\Support\Str::uuid(), 'category' => 'user']
    );

    $user = User::factory()->create();

    expect($user->can('core.users.read'))->toBeFalse();
    expect($user->can('read user'))->toBeFalse();
});

test('gate resolves new-style ability when legacy row is absent', function (): void {
    // Phase 3.4 will delete legacy rows. The Gate::before alias bridge must
    // not throw PermissionDoesNotExist when the legacy row no longer exists —
    // it should fall through to Spatie's normal check against the new-style
    // permission row.
    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::query()->firstOrCreate(
        ['name' => 'core.users.read'],
        ['uuid' => (string) Illuminate\Support\Str::uuid(), 'category' => 'users']
    );

    $user = User::factory()->create();
    $user->givePermissionTo('core.users.read');

    expect($user->can('core.users.read'))->toBeTrue();
});
