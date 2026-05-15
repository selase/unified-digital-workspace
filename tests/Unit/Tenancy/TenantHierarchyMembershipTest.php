<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantResolver;
use Illuminate\Http\Request;

/**
 * Ancestor-aware membership: an admin on a parent tenant can resolve a
 * child tenant context without an explicit pivot row on the child. We
 * call the private validateMembership method via reflection to verify
 * each branch in isolation.
 */
function invokeValidateMembership(TenantResolver $resolver, Tenant $tenant, Request $request): void
{
    $method = new ReflectionMethod($resolver, 'validateMembership');
    $method->setAccessible(true);
    $method->invoke($resolver, $tenant, $request);
}

test('direct membership passes', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user);

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    invokeValidateMembership(app(TenantResolver::class), $tenant->fresh(), $request);
})->throwsNoExceptions();

test('user with no membership at child or ancestors is rejected', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    invokeValidateMembership(app(TenantResolver::class), $tenant->fresh(), $request);
})->throws(App\Exceptions\TenantMembershipException::class);

test('ancestor membership passes for descendant request', function (): void {
    $parent = Tenant::factory()->create(['parent_id' => null]);
    $child = Tenant::factory()->create(['parent_id' => $parent->id]);

    $user = User::factory()->create();
    $parent->users()->attach($user);
    // NOTE: deliberately NO direct child membership.

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    invokeValidateMembership(app(TenantResolver::class), $child->fresh(), $request);
})->throwsNoExceptions();

test('grandparent membership passes for grandchild request', function (): void {
    $root = Tenant::factory()->create(['parent_id' => null]);
    $middle = Tenant::factory()->create(['parent_id' => $root->id]);
    $leaf = Tenant::factory()->create(['parent_id' => $middle->id]);

    $user = User::factory()->create();
    $root->users()->attach($user);

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    invokeValidateMembership(app(TenantResolver::class), $leaf->fresh(), $request);
})->throwsNoExceptions();

test('sibling membership does NOT grant access', function (): void {
    $parent = Tenant::factory()->create(['parent_id' => null]);
    $siblingA = Tenant::factory()->create(['parent_id' => $parent->id]);
    $siblingB = Tenant::factory()->create(['parent_id' => $parent->id]);

    $user = User::factory()->create();
    $siblingA->users()->attach($user);
    // User is only on siblingA — should NOT pass for siblingB.

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    invokeValidateMembership(app(TenantResolver::class), $siblingB->fresh(), $request);
})->throws(App\Exceptions\TenantMembershipException::class);
