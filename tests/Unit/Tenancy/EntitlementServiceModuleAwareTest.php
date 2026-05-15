<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Services\Tenancy\EntitlementService;

/**
 * EntitlementService::getAllowedPermissionsForTenant returns the union of
 * baseline permissions and permissions declared by every enabled module.
 * Exercising the candidate-permission resolution directly via reflection
 * lets us assert the union logic without standing up a full tenant + module
 * pivot for every case.
 */
function invokeCandidateNames(string $tenantId): array
{
    $service = app(EntitlementService::class);
    $method = new ReflectionMethod($service, 'candidatePermissionNames');
    $method->setAccessible(true);

    return (array) $method->invoke($service, $tenantId);
}

test('candidate names default to the baseline when tenant has no enabled modules', function (): void {
    $names = invokeCandidateNames('not-a-real-tenant-id');

    expect($names)->toBe(Permission::BASELINE_TENANT_PERMISSIONS);
});

test('cache key is namespaced per tenant', function (): void {
    expect(EntitlementService::allowedPermissionsCacheKey('abc-123'))
        ->toBe('tenant_abc-123_allowed_permissions');
});

test('forgetAllowedPermissions removes the cached entry', function (): void {
    $key = EntitlementService::allowedPermissionsCacheKey('tenant-xyz');
    cache()->put($key, ['placeholder'], 60);

    EntitlementService::forgetAllowedPermissions('tenant-xyz');

    expect(cache()->has($key))->toBeFalse();
});

test('baseline list still exposed via TENANT_SAFE for backwards compatibility', function (): void {
    expect(Permission::TENANT_SAFE)->toBe(Permission::BASELINE_TENANT_PERMISSIONS);
});

test('baseline includes the core tenant operations under module.action.scope naming', function (): void {
    expect(Permission::BASELINE_TENANT_PERMISSIONS)
        ->toContain('core.dashboard.access')
        ->toContain('core.users.read')
        ->toContain('core.settings.manage');
});
