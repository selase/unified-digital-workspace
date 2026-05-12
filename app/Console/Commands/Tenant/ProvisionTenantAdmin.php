<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenant;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent provisioning of a user as an admin on a tenant:
 *
 *   1. Attach the user to the tenant_user pivot.
 *   2. Assign the given role (default: Org Superadmin) scoped to the tenant team.
 *   3. Grant the union of permissions exposed by every enabled module on
 *      the tenant — so the sidebar reflects what the admin can actually use.
 *
 * Useful for one-off recovery (e.g. seeded tenant with no admin yet) and as
 * the underlying primitive for any future "create tenant + first admin" flow.
 */
final class ProvisionTenantAdmin extends Command
{
    /** @var string */
    protected $signature = 'tenant:provision-admin
        {tenant : Tenant slug or UUID}
        {email : Email address of the user to provision}
        {--role=Org Superadmin : Role to assign (must already exist)}';

    /** @var string */
    protected $description = 'Attach a user to a tenant, assign a role, and grant permissions from every enabled module';

    public function handle(ModuleManager $moduleManager): int
    {
        $identifier = (string) $this->argument('tenant');
        $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier) === 1;
        $tenant = $isUuid
            ? Tenant::query()->find($identifier)
            : Tenant::query()->where('slug', $identifier)->first();

        if (! $tenant) {
            $this->error("Tenant not found: {$identifier}");

            return self::FAILURE;
        }

        $user = User::query()->where('email', (string) $this->argument('email'))->first();

        if (! $user) {
            $this->error("User not found: {$this->argument('email')}");

            return self::FAILURE;
        }

        $role = (string) $this->option('role');

        $tenant->users()->syncWithoutDetaching([$user->id]);
        $this->info("Linked {$user->email} -> {$tenant->name}");

        $previousTeamId = app(PermissionRegistrar::class)->getPermissionsTeamId();
        setPermissionsTeamId($tenant->id);

        try {
            $user->assignRole($role);
            $this->info("Assigned role '{$role}' (scoped to {$tenant->slug})");

            $declared = $moduleManager->getEnabledForTenant($tenant)
                ->flatMap(fn (array $module): array => $module['permissions'] ?? [])
                ->unique()
                ->values()
                ->all();

            $existing = Permission::query()
                ->whereIn('name', $declared)
                ->where('guard_name', 'web')
                ->pluck('name')
                ->all();

            $missing = array_diff($declared, $existing);

            if ($existing !== []) {
                $user->givePermissionTo($existing);
                $this->info('Granted '.count($existing).' module permission(s).');
            } else {
                $this->warn('No module permissions to grant — modules may not have been synced yet.');
            }

            if ($missing !== []) {
                $this->warn('Skipped (not yet seeded): '.implode(', ', $missing));
                $this->line('  Run `php artisan db:seed --class=PermissionsSeeder` or re-enable affected modules to create them.');
            }
        } finally {
            setPermissionsTeamId($previousTeamId);
        }

        return self::SUCCESS;
    }
}
