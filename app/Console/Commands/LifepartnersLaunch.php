<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantBillingAgreement;
use App\Models\TenantModule;
use App\Models\User;
use App\Modules\SiteLifepartnersPlatform\Services\LifepartnersPreviewImporter;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class LifepartnersLaunch extends Command
{
    protected $signature = 'cms:lifepartners-launch
        {--execute : Create the tenant and import; otherwise only describe the inventory}
        {--editor=* : Existing user emails who become tenant editors}';

    protected $description = 'Create the production Life Partners Platform tenant and import the audited six-page source';

    public function handle(LifepartnersPreviewImporter $importer): int
    {
        if (! $this->option('execute')) {
            $this->info('Dry run: tenant lifepartners-platform on lifepartnersplatform.org, six CMS pages, nine original images. No writes.');

            return self::SUCCESS;
        }

        $editors = User::query()->whereIn('email', (array) $this->option('editor'))->get();
        if ($editors->isEmpty() || $editors->count() !== count((array) $this->option('editor'))) {
            $this->error('Every --editor must be an existing user; none are created.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->firstOrNew(['slug' => 'lifepartners-platform']);
        if ($tenant->exists && (($tenant->meta['migration_launch'] ?? null) !== 'lifepartners'
            || TenantBillingAgreement::query()->where('tenant_id', $tenant->id)->exists())) {
            $this->error('Existing tenant is not the unbilled Lifepartners launch tenant; no changes made.');

            return self::FAILURE;
        }
        if (! $tenant->exists) {
            $tenant->fill([
                'name' => 'Life Partners Platform', 'status' => 'active',
                'isolation_mode' => 'shared', 's3_mode' => 'shared', 'country' => 'Ghana',
                'custom_domain' => 'lifepartnersplatform.org', 'custom_domain_status' => 'active',
                'meta' => ['migration_launch' => 'lifepartners', 'launched' => true, 'source_domain' => 'lifepartnersplatform.org'],
            ])->save();
        }
        $tenant->users()->syncWithoutDetaching($editors->pluck('id')->all());
        foreach (['cms-core', 'site-lifepartners-platform'] as $module) {
            TenantModule::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'module_slug' => $module],
                ['is_enabled' => true, 'version' => '1.0.0'],
            );
        }

        $team = app(PermissionRegistrar::class)->getPermissionsTeamId();
        try {
            setPermissionsTeamId($tenant->id);
            $permissions = [
                'core.dashboard.access', 'cms.posts.view', 'cms.posts.create', 'cms.posts.update', 'cms.posts.delete',
                'cms.post_types.view', 'cms.media.view', 'cms.media.manage', 'cms.menus.view',
            ];
            foreach ($permissions as $name) {
                Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['category' => 'cms']);
            }
            $role = Role::query()->firstOrCreate(['name' => 'Lifepartners Editor', 'tenant_id' => $tenant->id, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
            foreach ($editors as $editor) {
                $editor->assignRole($role);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->info(json_encode($importer->import($tenant, $editors->first()), JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Launch import failed: '.$exception::class);
            report($exception);

            return self::FAILURE;
        } finally {
            setPermissionsTeamId($team);
        }
    }
}
