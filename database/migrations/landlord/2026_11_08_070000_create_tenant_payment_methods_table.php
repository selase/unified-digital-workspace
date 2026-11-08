<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('tenant_payment_methods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('provider', 20)->default('paystack');
            $table->text('authorization_code');
            $table->string('signature', 120);
            $table->string('email', 254);
            $table->string('brand', 40)->nullable();
            $table->string('last4', 4)->nullable();
            $table->string('exp_month', 2)->nullable();
            $table->string('exp_year', 4)->nullable();
            $table->timestamp('consented_at');
            $table->string('consent_version', 20);
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'signature']);
            $table->index(['tenant_id', 'removed_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('tenant_payment_methods');
    }
};
