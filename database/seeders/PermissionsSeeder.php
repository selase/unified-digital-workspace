<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Libraries\RolePermissions;
use App\Services\Auth\AbilityAliasService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the canonical permission rows using the module.action.scope naming
 * convention (e.g. core.users.read, admin.tenants.create).
 *
 * The legacy "verb noun" names (e.g. "read user", "create tenant") that this
 * seeder previously created live on as DB rows produced by the mirror
 * migration `2026_05_15_063932_insert_module_style_permission_names` for
 * backward compatibility. The cutover migration in Phase 3.4 will drop those
 * legacy rows; until then, AbilityAliasService bridges any straggling caller.
 */
final class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions() as $permission) {
            Permission::query()->updateOrCreate([
                'name' => $permission['name'],
            ], [
                'uuid' => Str::uuid(),
                'name' => $permission['name'],
                'category' => $permission['category'],
            ]);
        }

        /** Assign created permissions to roles */
        RolePermissions::assign();
    }

    /**
     * @return array<int, array{name: string, category: string}>
     */
    private function permissions(): array
    {
        return array_map(
            fn (string $name): array => [
                'name' => $name,
                'category' => $this->categoryFor($name),
            ],
            array_keys(AbilityAliasService::all()),
        );
    }

    /**
     * Derive a category from a module.action.scope ability name.
     *
     * Examples:
     *   core.users.read       -> users
     *   admin.audit-trail.read -> audit-trail
     *   core.settings.manage  -> settings
     */
    private function categoryFor(string $ability): string
    {
        $parts = explode('.', $ability);

        return $parts[1] ?? $ability;
    }
}
