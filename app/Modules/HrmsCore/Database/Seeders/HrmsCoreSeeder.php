<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Database\Seeders;

use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

final class HrmsCoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->bound(TenantContext::class)) {
            return;
        }

        $tenant = app(TenantContext::class)->getTenant();

        if (! $tenant) {
            return;
        }

        $tenantConnection = config('database.default_tenant_connection', 'tenant');

        if (! Schema::connection($tenantConnection)->hasTable('hrms_grades')) {
            return;
        }

        $this->call([
            GradeSeeder::class,
            LeaveCategorySeeder::class,
        ]);
    }
}
