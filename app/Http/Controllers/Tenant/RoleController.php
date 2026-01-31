<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class RoleController extends Controller
{
    public function index(string $subdomain): View|JsonResponse
    {
        $this->authorize('read role');
        $tenant = $this->getTenant();

        // Spatie Team logic: fetch roles where tenant_id is this tenant OR null (global)
        $roles = Role::where(function ($query) use ($tenant) {
            $query->where('tenant_id', $tenant->id)
                ->orWhereNull('tenant_id');
        })->get();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json($roles);
        }

        return view('tenant.roles.index', compact('roles'));
    }

    public function create(string $subdomain): View
    {
        $this->authorize('create role');
        $tenant = $this->getTenant();
        $allowedPermissionNames = app(\App\Services\Tenancy\EntitlementService::class)->getAllowedPermissionsForTenant($tenant->id);
        $permissions = Permission::whereIn('name', $allowedPermissionNames)->get();

        return view('tenant.roles.create', compact('permissions'));
    }

    public function show(string $subdomain, string $id): View|JsonResponse
    {
        $this->authorize('read role');
        $tenant = $this->getTenant();

        $role = Role::where(function ($query) use ($tenant) {
            $query->where('tenant_id', $tenant->id)
                ->orWhereNull('tenant_id');
        })->where('id', $id)->firstOrFail();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json($role->load('permissions'));
        }

        return view('tenant.roles.show', compact('role'));
    }

    public function edit(string $subdomain, string $id): View
    {
        $this->authorize('update role');
        $tenant = $this->getTenant();
        $role = Role::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $allowedPermissionNames = app(\App\Services\Tenancy\EntitlementService::class)->getAllowedPermissionsForTenant($tenant->id);
        $permissions = Permission::whereIn('name', $allowedPermissionNames)->get();

        return view('tenant.roles.edit', compact('role', 'permissions'));
    }

    public function store(Request $request, string $subdomain): RedirectResponse|JsonResponse
    {
        $this->authorize('create role');
        $tenant = $this->getTenant();

        $allowedPermissionNames = app(\App\Services\Tenancy\EntitlementService::class)->getAllowedPermissionsForTenant($tenant->id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                Rule::unique('roles')->where(function ($query) use ($tenant) {
                    return $query->where('tenant_id', $tenant->id);
                }),
            ],
            'permissions' => 'array',
            'permissions.*' => [
                'string',
                Rule::in($allowedPermissionNames),
            ],
        ]);

        return DB::transaction(function () use ($validated, $tenant, $request) {
            // Ensure we set the team ID for Spatie
            setPermissionsTeamId($tenant->id);

            $role = Role::create([
                'name' => $validated['name'],
                'tenant_id' => $tenant->id,
                'guard_name' => 'web',
            ]);

            if (isset($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }

            if ($request->wantsJson()) {
                return response()->json($role->load('permissions'));
            }

            return redirect()->route('tenant.roles.index', ['subdomain' => $tenant->slug])->with('success', 'Role created successfully.');
        });
    }

    public function update(Request $request, string $subdomain, string $id): RedirectResponse|JsonResponse
    {
        $this->authorize('update role');
        $tenant = $this->getTenant();

        $role = Role::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($role->isSystemRole()) {
            return response()->json(['message' => 'Cannot modify system roles.'], 403);
        }

        $allowedPermissionNames = app(\App\Services\Tenancy\EntitlementService::class)->getAllowedPermissionsForTenant($tenant->id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                Rule::unique('roles')->ignore($role->id)->where(function ($query) use ($tenant) {
                    return $query->where('tenant_id', $tenant->id);
                }),
            ],
            'permissions' => 'array',
            'permissions.*' => [
                'string',
                Rule::in($allowedPermissionNames),
            ],
        ]);

        return DB::transaction(function () use ($role, $validated, $tenant, $request) {
            setPermissionsTeamId($tenant->id);

            $role->update([
                'name' => $validated['name'],
            ]);

            if (isset($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }

            if ($request->wantsJson()) {
                return response()->json($role->load('permissions'));
            }

            return redirect()->route('tenant.roles.index', ['subdomain' => $tenant->slug])->with('success', 'Role updated successfully.');
        });
    }

    public function destroy(string $subdomain, string $id): JsonResponse|RedirectResponse
    {
        $this->authorize('delete role');
        $tenant = $this->getTenant();

        $role = Role::where(function ($q) use ($tenant) {
            $q->where('tenant_id', $tenant->id)
                ->orWhereNull('tenant_id');
        })
            ->where('id', $id)
            ->firstOrFail();

        if ($role->isSystemRole()) {
            return response()->json(['message' => 'Cannot delete system roles.'], 403);
        }

        $role->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Role deleted successfully.']);
        }

        return redirect()->route('tenant.roles.index', ['subdomain' => $tenant->slug])->with('success', 'Role deleted successfully.');
    }

    protected function getTenant()
    {
        $tenant = app(TenantContext::class)->getTenant();
        if (! $tenant) {
            abort(403, 'Tenant context not resolved.');
        }

        return $tenant;
    }
}
