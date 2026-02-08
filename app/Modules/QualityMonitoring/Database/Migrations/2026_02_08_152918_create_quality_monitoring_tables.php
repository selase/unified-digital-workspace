<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qm_workplans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id')->index();
            $table->string('title');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('draft');
            $table->uuid('owner_id')->nullable()->index();
            $table->jsonb('org_scope')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletesTz();
        });

        Schema::create('qm_workplan_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('workplan_id')->constrained('qm_workplans')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('status')->default('draft');
            $table->jsonb('payload')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['workplan_id', 'version_no']);
        });

        Schema::create('qm_objectives', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('workplan_id')->constrained('qm_workplans')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('weight')->default(0);
            $table->string('status')->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('qm_activities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('objective_id')->constrained('qm_objectives')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->uuid('responsible_id')->nullable()->index();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('weight')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('qm_indicators', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->string('type')->default('output');
            $table->string('unit')->nullable();
            $table->text('definition')->nullable();
            $table->text('formula_notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('qm_data_sources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('method')->nullable();
            $table->string('custodian')->nullable();
            $table->text('quality_notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('qm_kpis', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('activity_id')->constrained('qm_activities')->cascadeOnDelete();
            $table->foreignId('indicator_id')->nullable()->constrained('qm_indicators')->nullOnDelete();
            $table->string('name');
            $table->string('unit')->nullable();
            $table->decimal('target_value', 14, 2)->nullable();
            $table->decimal('baseline_value', 14, 2)->nullable();
            $table->string('direction')->default('increase');
            $table->jsonb('calculation')->nullable();
            $table->string('frequency')->nullable();
            $table->timestamps();
        });

        Schema::create('qm_kpi_updates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('kpi_id')->constrained('qm_kpis')->cascadeOnDelete();
            $table->decimal('value', 14, 2)->nullable();
            $table->timestampTz('captured_at')->nullable();
            $table->text('note')->nullable();
            $table->uuid('captured_by_id')->nullable()->index();
            $table->string('evidence_path')->nullable();
            $table->string('evidence_mime')->nullable();
            $table->unsignedBigInteger('evidence_size')->default(0);
            $table->timestamps();
        });

        Schema::create('qm_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('workplan_id')->constrained('qm_workplans')->cascadeOnDelete();
            $table->uuid('reviewer_id')->nullable()->index();
            $table->string('status')->default('pending');
            $table->text('comments')->nullable();
            $table->jsonb('scores')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('qm_alerts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('workplan_id')->constrained('qm_workplans')->cascadeOnDelete();
            $table->foreignId('kpi_id')->nullable()->constrained('qm_kpis')->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('open');
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('qm_variances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('workplan_id')->constrained('qm_workplans')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('qm_activities')->nullOnDelete();
            $table->foreignId('kpi_id')->nullable()->constrained('qm_kpis')->nullOnDelete();
            $table->string('category')->nullable();
            $table->string('impact_level')->nullable();
            $table->text('narrative')->nullable();
            $table->text('corrective_action')->nullable();
            $table->date('revised_date')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('evidence_mime')->nullable();
            $table->unsignedBigInteger('evidence_size')->default(0);
            $table->string('status')->default('pending');
            $table->uuid('reviewed_by_id')->nullable()->index();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qm_variances');
        Schema::dropIfExists('qm_alerts');
        Schema::dropIfExists('qm_reviews');
        Schema::dropIfExists('qm_kpi_updates');
        Schema::dropIfExists('qm_kpis');
        Schema::dropIfExists('qm_data_sources');
        Schema::dropIfExists('qm_indicators');
        Schema::dropIfExists('qm_activities');
        Schema::dropIfExists('qm_objectives');
        Schema::dropIfExists('qm_workplan_versions');
        Schema::dropIfExists('qm_workplans');
    }
};
