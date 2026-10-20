<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->table('packages', function (Blueprint $table): void {
            $table->decimal('price_ghs', 10, 2)->nullable();
        });
        Schema::connection('landlord')->table('tenants', function (Blueprint $table): void {
            $table->string('billing_currency', 3)->nullable();
        });
        Schema::connection('landlord')->table('usage_prices', function (Blueprint $table): void {
            $table->dropUnique(['target_type', 'target_id', 'metric']);
            $table->unique(['target_type', 'target_id', 'metric', 'currency'], 'usage_prices_target_metric_currency_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('usage_prices', function (Blueprint $table): void {
            $table->dropUnique('usage_prices_target_metric_currency_unique');
            $table->unique(['target_type', 'target_id', 'metric']);
        });
        Schema::connection('landlord')->table('tenants', function (Blueprint $table): void {
            $table->dropColumn('billing_currency');
        });
        Schema::connection('landlord')->table('packages', function (Blueprint $table): void {
            $table->dropColumn('price_ghs');
        });
    }
};
