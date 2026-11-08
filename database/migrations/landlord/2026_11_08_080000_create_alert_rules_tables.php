<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('alert_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('event', 80);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->json('channels');
            $table->json('recipients')->nullable();
            $table->json('conditions')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'event', 'is_active']);
        });

        Schema::connection('landlord')->create('alert_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('alert_rule_id')->constrained('alert_rules')->cascadeOnDelete();
            $table->string('event', 80);
            $table->string('dedup_key', 191);
            $table->string('recipient', 254);
            $table->json('channels');
            $table->timestamps();
            $table->unique(['alert_rule_id', 'dedup_key', 'recipient']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('alert_deliveries');
        Schema::connection('landlord')->dropIfExists('alert_rules');
    }
};
