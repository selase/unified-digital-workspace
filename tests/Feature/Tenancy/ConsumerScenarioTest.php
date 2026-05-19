<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ConsumerScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_llm_config_api_key_encryption()
    {
        $tenant = \App\Models\Tenant::factory()->create();

        $config = \App\Models\TenantLlmConfig::create([
            'tenant_id' => $tenant->id,
            'provider' => 'openai',
            'api_key_encrypted' => 'sk-1234567890',
        ]);

        // Verify it is encrypted in the database
        $raw = DB::connection('landlord')->table('tenant_llm_configs')->where('id', $config->id)->value('api_key_encrypted');
        $this->assertNotEquals('sk-1234567890', $raw);

        // Verify we can decrypt it
        $this->assertEquals('sk-1234567890', $config->getDecryptedKey());
    }

    public function test_byo_database_configuration_via_secrets()
    {
        // 1. Setup mock secret in storage
        $secrets = [
            'tenant-db-ref' => [
                'type' => 'db',
                'host' => '1.2.3.4',
                'port' => 3306,
                'database' => 'external_db',
                'username' => 'ext_user',
                'password' => 'ext_pass',
            ],
        ];
        file_put_contents(storage_path('secrets.json'), json_encode($secrets));

        // 2. Create BYO Tenant
        $tenant = \App\Models\Tenant::factory()->create([
            'isolation_mode' => 'byo',
            'db_driver' => 'mysql',
            'db_secret_ref' => 'tenant-db-ref',
        ]);

        // 3. Configure DB
        app(TenantDatabaseManager::class)->configure($tenant);

        // 4. Assert config is correct
        $config = config('database.connections.tenant');
        $this->assertEquals('1.2.3.4', $config['host']);
        $this->assertEquals('external_db', $config['database']);
        $this->assertEquals('ext_user', $config['username']);

        // Clean up
        @unlink(storage_path('secrets.json'));
    }

    public function test_migration_compliance_check_for_kms()
    {
        // 1. Create Tenant with encryption enabled but no KMS key
        $tenant = \App\Models\Tenant::factory()->create([
            'isolation_mode' => 'db_per_tenant',
            'encryption_at_rest' => true,
            'kms_key_ref' => null,
            'status' => \App\Enum\TenantStatusEnum::ACTIVE,
        ]);

        // 2. Run migration command
        \Illuminate\Support\Facades\Artisan::call('tenants:migrate', [
            '--tenant' => $tenant->id,
        ]);

        // 3. Assert it was skipped
        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString('Skipping tenant', $output);
        $this->assertStringContainsString('no KMS key ref', $output);

        // Verify skipped record in database
        $run = DB::connection('landlord')->table('tenant_migration_runs')->where('tenant_id', $tenant->id)->first();
        $this->assertEquals('skipped', $run->status);
    }
}
