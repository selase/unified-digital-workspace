<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workflow_rules')) {
            Schema::create('workflow_rules', function (Blueprint $table) {
                $table->id();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('trigger_event'); // e.g. incident.logged, leave.submitted, crm.inquiry.created, store.order.paid
                $table->json('conditions')->nullable(); // array of [field, operator, value]
                $table->json('actions'); // array of action definitions
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('execution_count')->default(0);
                $table->uuid('created_by_user_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('workflow_execution_logs')) {
            Schema::create('workflow_execution_logs', function (Blueprint $table) {
                $table->id();
                $table->uuid('tenant_id')->index();
                $table->foreignId('workflow_rule_id')->constrained('workflow_rules')->cascadeOnDelete();
                $table->string('trigger_event');
                $table->json('context_payload')->nullable();
                $table->json('actions_executed')->nullable();
                $table->string('status')->default('success'); // success, failed, loop_aborted
                $table->unsignedTinyInteger('execution_depth')->default(1);
                $table->text('error_message')->nullable();
                $table->timestamp('executed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_execution_logs');
        Schema::dropIfExists('workflow_rules');
    }
};
