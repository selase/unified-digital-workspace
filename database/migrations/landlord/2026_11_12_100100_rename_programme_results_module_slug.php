<?php

declare(strict_types=1);

use App\Services\Tenancy\EntitlementService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        $this->rename('programme-results', 'results-framework', 'NGO M&E', 'Results Framework');
    }

    public function down(): void
    {
        $this->rename('results-framework', 'programme-results', 'Results Framework', 'NGO M&E');
    }

    private function rename(string $from, string $to, string $oldLabel, string $newLabel): void
    {
        $db = DB::connection('landlord');
        $featureKeys = ["module-{$from}" => "module-{$to}", "{$from}.manage" => "{$to}.manage"];

        $tenantIds = $db->transaction(function () use ($db, $featureKeys, $from, $to, $oldLabel, $newLabel): array {
            $moduleRows = $db->table('tenant_modules')->where('module_slug', $from);
            $moduleFeatures = $db->table('tenant_features')->where('meta->module_slug', $from);
            $tenantIds = $moduleRows->pluck('tenant_id')->merge($moduleFeatures->pluck('tenant_id'));

            $db->table('permissions')
                ->whereIn('name', ['results.view', 'results.manage'])
                ->where('category', $from)
                ->update(['category' => $to]);

            $moduleRows->update(['module_slug' => $to]);
            $moduleFeatures->update(['meta->module_slug' => $to]);

            foreach ($featureKeys as $oldKey => $newKey) {
                $tenantFeatures = $db->table('tenant_features')->where('feature_key', $oldKey);
                $tenantIds = $tenantIds->merge($tenantFeatures->pluck('tenant_id'));
                $tenantFeatures->update(['feature_key' => $newKey]);

                $features = $db->table('features')->where('slug', $oldKey);
                (clone $features)->where('name', $oldLabel)->update(['name' => $newLabel]);
                $features->update(['slug' => $newKey]);
            }

            return $tenantIds->unique()->all();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($tenantIds as $tenantId) {
            Cache::forget("tenant.{$tenantId}.enabled_modules");
            foreach ([$from, $to] as $slug) {
                Cache::forget("tenant.{$tenantId}.module.{$slug}");
            }
            foreach ([...array_keys($featureKeys), ...array_values($featureKeys)] as $key) {
                Cache::forget("tenant_{$tenantId}_feature_{$key}");
            }
            EntitlementService::forgetAllowedPermissions($tenantId);
        }
    }
};
