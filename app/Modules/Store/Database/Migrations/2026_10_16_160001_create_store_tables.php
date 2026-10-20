<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_credit_wallets')) {
            Schema::create('tenant_credit_wallets', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('credit_type')->index(); // sms, email, ai_token
                $table->unsignedBigInteger('balance')->default(0);
                $table->unsignedBigInteger('total_purchased')->default(0);
                $table->unsignedBigInteger('total_consumed')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'credit_type']);
            });
        }

        if (! Schema::hasTable('store_products')) {
            Schema::create('store_products', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->string('slug');
                $table->string('sku')->nullable();
                $table->text('description')->nullable();
                $table->unsignedInteger('price_cents');
                $table->string('currency', 3)->default('GHS');
                $table->integer('stock_quantity')->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('audience')->default('staff_only'); // staff_only, public
                $table->timestamps();

                $table->unique(['tenant_id', 'slug']);
            });
        }

        if (! Schema::hasTable('store_orders')) {
            Schema::create('store_orders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('order_number');
                $table->string('user_id')->nullable()->index(); // user UUID
                $table->string('customer_name');
                $table->string('customer_email');
                $table->unsignedInteger('total_amount_cents');
                $table->string('currency', 3)->default('GHS');
                $table->string('status')->default('pending')->index(); // pending, paid, fulfilled, cancelled
                $table->string('payment_method')->default('portal_balance');
                $table->timestamps();

                $table->unique(['tenant_id', 'order_number']);
            });
        }

        if (! Schema::hasTable('store_order_items')) {
            Schema::create('store_order_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('store_order_id')->constrained('store_orders')->cascadeOnDelete();
                $table->foreignId('store_product_id')->constrained('store_products')->cascadeOnDelete();
                $table->unsignedInteger('quantity');
                $table->unsignedInteger('unit_price_cents');
                $table->unsignedInteger('total_price_cents');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_order_items');
        Schema::dropIfExists('store_orders');
        Schema::dropIfExists('store_products');
        Schema::dropIfExists('tenant_credit_wallets');
    }
};
