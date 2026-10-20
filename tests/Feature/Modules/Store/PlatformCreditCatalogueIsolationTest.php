<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\Store\Models\PlatformCreditPackage;
use App\Modules\Store\Models\PlatformCreditPurchase;
use App\Modules\Store\Services\PlatformCreditService;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use App\Services\Tenancy\TenantProvisioner;
use Database\Seeders\RecommendedPlatformCreditPackageSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('purchases landlord email packs from a dedicated database and repairs legacy local catalogue foreign keys', function (): void {
    $this->seed(RecommendedPlatformCreditPackageSeeder::class);
    $pack = PlatformCreditPackage::query()->where('currency', 'GHS')->sole();
    $tenant = Tenant::factory()->create(['isolation_mode' => 'db_per_tenant', 'db_driver' => 'pgsql']);
    $database = 'tenant_'.str_replace('-', '_', (string) $tenant->id);
    $originalConnection = DB::getDefaultConnection();

    try {
        app(TenantProvisioner::class)->provision($tenant);
        $tenant->refresh();
        app(TenantContext::class)->setTenant($tenant);
        app(TenantDatabaseManager::class)->configure($tenant);
        app(ModuleManager::class)->enableForTenantWithMigrations('store', $tenant);
        DB::setDefaultConnection('tenant');

        expect(Schema::connection('tenant')->hasTable('platform_credit_packages'))->toBeFalse();
        $wallet = app(PlatformCreditService::class)->purchasePackage($tenant->id, $pack->id, 'dedicated-email-pack');
        expect($wallet->balance)->toBe(1000);
        $purchase = PlatformCreditPurchase::query()->with('package')->sole();
        expect($purchase->package?->uuid)->toBe($pack->uuid)
            ->and($purchase->package?->getConnectionName())->toBe('landlord')
            ->and($purchase->amount_paid_cents)->toBe(1500);
        app(PlatformCreditService::class)->purchasePackage($tenant->id, $pack->id, 'dedicated-email-pack');
        expect($wallet->fresh()->balance)->toBe(1000);
        expect(PlatformCreditPurchase::query()->count())->toBe(1);
        expect(Schema::getForeignKeys('platform_credit_purchases'))->toBeEmpty();

        Schema::create('platform_credit_packages', function (Blueprint $table): void {
            $table->id();
        });
        DB::table('platform_credit_packages')->insert(['id' => $pack->id]);
        Schema::table('platform_credit_purchases', function (Blueprint $table): void {
            $table->foreign('package_id')->references('id')->on('platform_credit_packages')->cascadeOnDelete();
        });
        $correction = require app_path('Modules/Store/Database/Migrations/2026_10_20_100001_fix_platform_credit_purchase_catalogue_reference.php');
        $correction->up();
        $correction->up();
        expect(Schema::getForeignKeys('platform_credit_purchases'))->toBeEmpty();
        expect(Schema::hasIndex('platform_credit_purchases', ['package_id']))->toBeTrue();
        expect(PlatformCreditPurchase::query()->with('package')->sole()->package?->uuid)->toBe($pack->uuid);
    } finally {
        DB::setDefaultConnection($originalConnection);
        dropTenantDatabase($database);
        app(TenantContext::class)->clear();
        app(TenantDatabaseManager::class)->configureShared();
    }
});
