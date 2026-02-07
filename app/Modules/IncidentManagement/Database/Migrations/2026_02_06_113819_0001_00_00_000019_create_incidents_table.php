<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('title');
            $table->longText('description');
            $table->foreignId('category_id')->nullable()->constrained('incident_categories')->nullOnDelete();
            $table->foreignId('priority_id')->nullable()->constrained('incident_priorities')->nullOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('incident_statuses')->nullOnDelete();
            $table->uuid('reported_by_id')->nullable()->index();
            $table->foreignId('reporter_id')->nullable()->constrained('incident_reporters')->nullOnDelete();
            $table->string('reported_via')->default('internal');
            $table->uuid('assigned_to_id')->nullable()->index();
            $table->timestampTz('due_at')->nullable()->index();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->string('source')->nullable();
            $table->string('reference_code')->index();
            $table->jsonb('metadata')->nullable();
            $table->text('impact')->nullable();
            $table->softDeletesTz();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference_code']);
            $table->index(['tenant_id', 'status_id', 'priority_id']);
            $table->index(['tenant_id', 'assigned_to_id']);
            $table->index(['tenant_id', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
