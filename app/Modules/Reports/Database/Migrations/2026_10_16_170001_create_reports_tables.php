<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_definitions')) {
            Schema::create('report_definitions', function (Blueprint $table) {
                $table->id();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('data_source'); // allowlisted key e.g. incidents, crm_inquiries, etc.
                $table->json('selected_columns'); // array of column keys
                $table->json('filters')->nullable(); // array of [column, operator, value]
                $table->string('date_column')->nullable();
                $table->boolean('is_public')->default(false);
                $table->uuid('created_by_user_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('report_execution_logs')) {
            Schema::create('report_execution_logs', function (Blueprint $table) {
                $table->id();
                $table->uuid('tenant_id')->index();
                $table->foreignId('report_definition_id')->constrained('report_definitions')->cascadeOnDelete();
                $table->uuid('user_id')->nullable()->index();
                $table->string('export_format')->default('preview'); // preview, csv, pdf
                $table->unsignedInteger('rows_count')->default(0);
                $table->timestamp('executed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_execution_logs');
        Schema::dropIfExists('report_definitions');
    }
};
