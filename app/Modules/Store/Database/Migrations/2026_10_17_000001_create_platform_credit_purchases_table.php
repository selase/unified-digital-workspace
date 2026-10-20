<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_credit_purchases')) {
            Schema::create('platform_credit_purchases', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                if (Schema::hasTable('users') && Schema::hasTable('platform_credit_packages')) {
                    $table->foreignId('package_id')->constrained('platform_credit_packages')->cascadeOnDelete();
                } else {
                    $table->unsignedBigInteger('package_id')->index();
                }
                $table->string('payment_reference')->index();
                $table->unsignedInteger('amount_paid_cents');
                $table->unsignedBigInteger('credits_awarded');
                $table->string('status')->default('succeeded');
                $table->timestamps();

                $table->unique(['tenant_id', 'payment_reference']);
            });
        }

        if (Schema::hasTable('store_orders') && ! Schema::hasColumn('store_orders', 'payment_reference')) {
            Schema::table('store_orders', function (Blueprint $table): void {
                $table->string('payment_reference')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('store_orders') && Schema::hasColumn('store_orders', 'payment_reference')) {
            Schema::table('store_orders', function (Blueprint $table): void {
                $table->dropColumn('payment_reference');
            });
        }

        Schema::dropIfExists('platform_credit_purchases');
    }
};
