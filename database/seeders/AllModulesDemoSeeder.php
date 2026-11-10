<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\CmsCore\Database\Seeders\CmsCoreSeeder;
use App\Modules\Crm\Database\Seeders\CrmSeeder;
use App\Modules\DocumentManagement\Database\Seeders\DocumentManagementSeeder;
use App\Modules\FacilitiesManagement\Database\Seeders\FacilitiesManagementSeeder;
use App\Modules\Forms\Models\FormDefinition;
use App\Modules\Forms\Models\FormField;
use App\Modules\Forums\Database\Seeders\ForumsSeeder;
use App\Modules\HrmsCore\Database\Seeders\HrmsCoreDemoSeeder;
use App\Modules\HrmsCore\Database\Seeders\HrmsCoreSeeder;
use App\Modules\IncidentManagement\Database\Seeders\IncidentManagementSeeder;
use App\Modules\Intranet\Database\Seeders\IntranetSeeder;
use App\Modules\Memos\Database\Seeders\MemosSeeder;
use App\Modules\ProjectManagement\Database\Seeders\ProjectManagementSeeder;
use App\Modules\QualityMonitoring\Database\Seeders\QualityMonitoringSeeder;
use App\Modules\Store\Models\StoreProduct;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Turns on every workspace module (all but the client-site extensions) for the demo tenant and fills them with sample
 * records, so a prospective customer can see the whole product working. Pick the tenant with DEMO_TENANT.
 * Never runs in production, and each step is independent: one module failing does not stop the rest.
 *
 *   DEMO_TENANT=demo-foundation php artisan db:seed --class="Database\Seeders\AllModulesDemoSeeder"
 */
final class AllModulesDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('AllModulesDemoSeeder: refusing to write demo records into production.');

            return;
        }

        $tenant = Tenant::query()->where('slug', (string) config('app.demo_tenant'))->first();
        if (! $tenant instanceof Tenant) {
            $this->command?->warn('AllModulesDemoSeeder: tenant not found, skipping.');

            return;
        }

        app(TenantContext::class)->setTenant($tenant);
        setPermissionsTeamId($tenant->id);
        $databases = app(TenantDatabaseManager::class);
        $tenant->requiresDedicatedDb() ? $databases->configure($tenant) : $databases->configureShared();

        $modules = app(ModuleManager::class);
        $slugs = $modules->all()->keys()->reject(fn (string $slug): bool => str_starts_with($slug, 'site-'))->values();
        $enabled = [];
        foreach ($slugs as $slug) {
            TenantFeature::query()->updateOrCreate(['tenant_id' => $tenant->id, 'feature_key' => 'module-'.$slug], ['enabled' => true, 'meta' => ['source' => 'complimentary']]);
            $enabled[] = $this->attempt('enable '.$slug, function () use ($modules, $slug, $tenant): void {
                if ($slug !== 'core') {
                    $modules->enableForTenantWithMigrations($slug, $tenant);
                }
            }) ? $slug : null;
        }

        $this->grantPermissions($tenant, array_values(array_filter($enabled)));

        foreach ([
            HrmsCoreSeeder::class, HrmsCoreDemoSeeder::class, CmsCoreSeeder::class, CrmSeeder::class, DocumentManagementSeeder::class,
            MemosSeeder::class, IncidentManagementSeeder::class, ForumsSeeder::class, ProjectManagementSeeder::class,
            QualityMonitoringSeeder::class, FacilitiesManagementSeeder::class, IntranetSeeder::class,
        ] as $seeder) {
            $this->attempt('seed '.class_basename($seeder), fn () => $this->call($seeder));
        }

        $this->attempt('forms and store samples', fn () => $this->smallSamples($tenant));

        $this->command?->info('AllModulesDemoSeeder: '.count(array_filter($enabled)).' modules enabled for '.$tenant->slug.'.');
    }

    private function attempt(string $step, callable $action): bool
    {
        try {
            $action();

            return true;
        } catch (Throwable $error) {
            $this->command?->warn("AllModulesDemoSeeder: {$step} failed: ".mb_substr($error->getMessage(), 0, 140));

            return false;
        }
    }

    /**
     * @param  list<string>  $slugs
     */
    private function grantPermissions(Tenant $tenant, array $slugs): void
    {
        $modules = app(ModuleManager::class);
        $names = collect($slugs)->flatMap(fn (string $slug): array => (array) ($modules->find($slug)['permissions'] ?? []))->filter()->unique()->values()->all();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        setPermissionsTeamId($tenant->id);

        $permissions = Permission::query()->whereIn('name', $names)->get()->all();
        foreach ($tenant->users()->get() as $user) {
            if ($user instanceof User && $permissions !== []) {
                $user->givePermissionTo($permissions);
            }
        }
    }

    private function smallSamples(Tenant $tenant): void
    {
        $owner = $tenant->users()->first();
        $ownerUuid = $owner instanceof User ? (string) $owner->uuid : (string) Str::uuid();

        $form = FormDefinition::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'community-feedback'],
            ['title' => 'Community feedback', 'description' => 'Tell us how our programmes are working for you. Responses are anonymous.', 'type' => 'survey', 'target_audience' => 'public', 'status' => 'published', 'allow_anonymous' => true, 'single_response_per_user' => false, 'created_by' => $ownerUuid],
        );
        foreach ([['How would you rate the service you received?', 'rating', 'select', true, ['Very good', 'Good', 'Fair', 'Poor']], ['What could we do better?', 'suggestion', 'textarea', false, null], ['Which community are you from?', 'community', 'text', false, null]] as $order => [$label, $name, $type, $required, $options]) {
            FormField::query()->firstOrCreate(
                ['form_definition_id' => $form->id, 'field_name' => $name],
                ['tenant_id' => $tenant->id, 'label' => $label, 'field_type' => $type, 'is_required' => $required, 'options_json' => $options, 'sort_order' => $order],
            );
        }

        foreach ([['Hope Foundation tote bag', 'tote-bag', 'HF-TOTE', 4_500, 120], ['Annual report 2026 (printed)', 'annual-report', 'HF-AR26', 2_000, 300], ['Supporter T-shirt', 'supporter-tshirt', 'HF-TEE', 6_000, 80]] as [$name, $slug, $sku, $price, $stock]) {
            StoreProduct::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                ['name' => $name, 'sku' => $sku, 'description' => 'Proceeds support our programmes.', 'price_cents' => $price, 'currency' => 'GHS', 'stock_quantity' => $stock, 'is_active' => true, 'audience' => 'public'],
            );
        }
    }
}
