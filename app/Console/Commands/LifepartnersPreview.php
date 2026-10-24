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
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class LifepartnersPreview extends Command
{
    protected $signature = 'cms:lifepartners-preview
        {--execute : Create/import the local preview; otherwise only describe the inventory}
        {--editor= : Existing local member email or UUID}
        {--domain=lifepartners-preview.unified-digital-workspace.test : Local .test preview hostname}';

    protected $description = 'Build the isolated local Lifepartners preview from the audited six-page source';

    public function handle(LifepartnersPreviewImporter $importer): int
    {
        if (! app()->environment(['local', 'testing'])
            || ! in_array(config('database.connections.landlord.host', '127.0.0.1'), ['127.0.0.1', 'localhost', '::1'], true)) {
            $this->error('This preview command only operates on a local/testing database.');

            return self::FAILURE;
        }

        $domain = mb_strtolower((string) $this->option('domain'));
        if (! preg_match('/\A[a-z0-9.-]+\.test\z/', $domain)) {
            $this->error('Use an isolated .test hostname; live domains are not accepted.');

            return self::FAILURE;
        }
        if (! $this->option('execute')) {
            $this->info('Dry run: six CMS pages, nine original images, no forms/payments/signup. No writes.');
            $this->line('Use --execute --editor=<existing-local-user> to create the isolated preview.');

            return self::SUCCESS;
        }

        $identifier = (string) $this->option('editor');
        $editor = $identifier === '' ? null : User::query()
            ->where(Str::isUuid($identifier) ? 'uuid' : 'email', $identifier)->first();
        if (! $editor) {
            $this->error('Select an existing local editor; no user or password will be created.');

            return self::FAILURE;
        }
        $tenant = Tenant::query()->firstOrNew(['slug' => 'lifepartners-platform']);
        if ($tenant->exists && (($tenant->meta['migration_preview'] ?? null) !== 'lifepartners'
            || $tenant->custom_domain !== $domain || $tenant->isolation_mode !== 'shared'
            || TenantBillingAgreement::query()->where('tenant_id', $tenant->id)->exists())) {
            $this->error('Existing tenant is not this preview; no changes made.');

            return self::FAILURE;
        }
        if (! $tenant->exists) {
            $tenant->fill([
                'name' => 'Life Partners Platform — Preview', 'status' => 'active',
                'isolation_mode' => 'shared', 's3_mode' => 'shared',
                'country' => 'Ghana', 'custom_domain' => $domain, 'custom_domain_status' => 'active',
                'meta' => ['migration_preview' => 'lifepartners', 'source_domain' => 'lifepartnersplatform.org'],
            ])->save();
        }
        $tenant->users()->syncWithoutDetaching([$editor->id]);
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
            $role = Role::query()->firstOrCreate(['name' => 'Lifepartners Preview Editor', 'tenant_id' => $tenant->id, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
            $editor->assignRole($role);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $result = $importer->import($tenant, $editor);
            $this->info(json_encode($result, JSON_THROW_ON_ERROR));
            $this->line('Preview: http://'.$domain.'/');
            $this->comment('Local preview only. No live domain, billing agreement or outbound integration was changed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Preview import failed: '.$exception::class);
            report($exception);

            return self::FAILURE;
        } finally {
            setPermissionsTeamId($team);
        }
    }
}
