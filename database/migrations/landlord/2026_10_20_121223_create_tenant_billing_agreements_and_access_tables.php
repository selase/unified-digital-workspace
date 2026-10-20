<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('landlord');
        $schema->create('tenant_billing_agreements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->date('effective_from');
            $table->string('plan_slug');
            $table->string('currency', 3);
            $table->unsignedInteger('editor_seats');
            $table->boolean('setup_required')->default(false);
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['tenant_id', 'effective_from']);
        });
        $schema->create('tenant_billing_accesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('level');
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });
        $schema->create('tenant_billing_monthly_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->date('month');
            $table->string('level');
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id', 'month'], 'billing_monthly_user_unique');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('landlord');
        $schema->dropIfExists('tenant_billing_monthly_users');
        $schema->dropIfExists('tenant_billing_accesses');
        $schema->dropIfExists('tenant_billing_agreements');
    }
};
