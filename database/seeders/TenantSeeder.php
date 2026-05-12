<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

final class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Tenant::query()->updateOrCreate(['slug' => 'purpledot'], [
            'name' => 'Purpledot',
            'email' => 'hello@wearepurpledot.com',
            'status' => \App\Enum\TenantStatusEnum::ACTIVE,
            'isolation_mode' => 'shared',
            'db_driver' => 'pgsql',
            'country' => 'Ghana',
            'city' => 'Accra',
            'state' => 'Greater Accra',
        ]);

        Tenant::query()->updateOrCreate(['slug' => 'ugmc'], [
            'name' => 'UGMC',
            'email' => 'support@ugmedicalcentre.org',
            'status' => \App\Enum\TenantStatusEnum::ACTIVE,
            'isolation_mode' => 'db_per_tenant',
            'db_driver' => 'pgsql',
            'country' => 'Ghana',
            'city' => 'Accra',
            'state' => 'Greater Accra',
        ]);

        $tgf = Tenant::query()->updateOrCreate(['slug' => 'thyroid-ghana-foundation'], [
            'name' => 'Thyroid Ghana Foundation',
            'email' => 'info@thyroidghanafoundation.org',
            'status' => \App\Enum\TenantStatusEnum::ACTIVE,
            'isolation_mode' => 'shared',
            'db_driver' => 'pgsql',
            'country' => 'Ghana',
            'city' => 'Accra',
            'state' => 'Greater Accra',
        ]);

        $moduleManager = app(ModuleManager::class);
        foreach (['cms-core', 'site-thyroid-ghana-foundation'] as $slug) {
            if ($moduleManager->exists($slug)) {
                $moduleManager->enableForTenant($slug, $tgf);
            }
        }

        $this->provisionDefaultAdmin($tgf, 'dev@thyroidghanafoundation.org');
        $this->provisionDefaultAdmin($tgf, 'hiselase@gmail.com');

        Tenant::query()->updateOrCreate(['slug' => 'banned-tenant'], [
            'name' => 'Banned Corp',
            'email' => 'banned@example.com',
            'status' => \App\Enum\TenantStatusEnum::BANNED,
            'isolation_mode' => 'shared',
            'db_driver' => 'pgsql',
            'country' => 'Global',
            'city' => 'Banned City',
            'state' => 'Banned State',
        ]);
    }

    /**
     * Delegate to tenant:provision-admin so seeded tenants ship with an
     * accessible admin. Silently skipped when the target user doesn't exist
     * (e.g. the env hasn't seeded users yet).
     */
    private function provisionDefaultAdmin(Tenant $tenant, string $email): void
    {
        if (! User::query()->where('email', $email)->exists()) {
            return;
        }

        Artisan::call('tenant:provision-admin', [
            'tenant' => $tenant->slug,
            'email' => $email,
        ]);
    }
}
