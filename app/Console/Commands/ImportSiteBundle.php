<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantBillingAgreement;
use App\Models\TenantModule;
use App\Models\User;
use App\Modules\CmsCore\Services\SiteBundleImporter;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class ImportSiteBundle extends Command
{
    protected $signature = 'cms:import-site-bundle
        {module : Site module directory under app/Modules, e.g. SiteKunneo}
        {--execute : Create the tenant and import; otherwise only describe the bundle}
        {--editor=* : Existing user emails who become tenant editors}';

    protected $description = 'Create a legacy-site tenant from an audited bundle in a Site module and import its pages and media';

    public function handle(SiteBundleImporter $importer): int
    {
        $module = (string) $this->argument('module');
        $directory = app_path('Modules/'.$module.'/Database/Seeders/bundle');
        if (! preg_match('/\ASite[A-Za-z0-9]+\z/', $module) || ! is_file($directory.'/bundle.json')) {
            $this->error('Unknown site module or missing Database/Seeders/bundle/bundle.json.');

            return self::FAILURE;
        }
        $bundle = json_decode((string) file_get_contents($directory.'/bundle.json'), true, 512, JSON_THROW_ON_ERROR);
        $info = $bundle['tenant'];
        if (! $this->option('execute')) {
            $this->info(sprintf('Dry run: tenant %s on %s, %d pages, %d assets. No writes.', $info['slug'], $info['domain'], count($bundle['pages']), count($bundle['assets'])));

            return self::SUCCESS;
        }

        $emails = (array) $this->option('editor');
        $editors = User::query()->whereIn('email', $emails)->get();
        if ($emails === [] || $editors->count() !== count($emails)) {
            $this->error('Every --editor must be an existing user; none are created.');

            return self::FAILURE;
        }
        $tenant = Tenant::query()->firstOrNew(['slug' => $info['slug']]);
        if ($tenant->exists && (($tenant->meta['site_bundle'] ?? null) !== $info['slug']
            || TenantBillingAgreement::query()->where('tenant_id', $tenant->id)->exists())) {
            $this->error('Existing tenant is not this unbilled bundle tenant; no changes made.');

            return self::FAILURE;
        }
        if (! $tenant->exists) {
            $tenant->fill([
                'name' => $info['name'], 'status' => 'active', 'isolation_mode' => 'shared', 's3_mode' => 'shared',
                'country' => $info['country'] ?? 'Ghana', 'custom_domain' => $info['domain'], 'custom_domain_status' => 'active',
                'meta' => ['site_bundle' => $info['slug'], 'launched' => true],
            ])->save();
        }
        $tenant->users()->syncWithoutDetaching($editors->pluck('id')->all());
        foreach (['cms-core', $bundle['module_slug']] as $slug) {
            TenantModule::query()->firstOrCreate(['tenant_id' => $tenant->id, 'module_slug' => $slug], ['is_enabled' => true, 'version' => '1.0.0']);
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
            $role = Role::query()->firstOrCreate(['name' => $info['name'].' Editor', 'tenant_id' => $tenant->id, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
            foreach ($editors as $editor) {
                $editor->assignRole($role);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->info(json_encode($importer->import($tenant, $editors->first(), $directory), JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Import failed: '.$exception::class.': '.$exception->getMessage());
            report($exception);

            return self::FAILURE;
        } finally {
            setPermissionsTeamId($team);
        }
    }
}
