<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('form_definitions')) {
            Schema::create('form_definitions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('title');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->string('type')->default('general')->index(); // general, hr_request, survey, live_poll
                $table->string('target_audience')->default('public')->index(); // public, internal_only, departmental
                $table->unsignedBigInteger('target_department_id')->nullable()->index();
                $table->string('status')->default('draft')->index(); // draft, published, closed
                $table->boolean('allow_anonymous')->default(false);
                $table->boolean('single_response_per_user')->default(false);
                $table->dateTime('expires_at')->nullable();
                $table->string('created_by')->index(); // user UUID
                $table->timestamps();

                $table->unique(['tenant_id', 'slug']);
            });
        }

        if (! Schema::hasTable('form_fields')) {
            Schema::create('form_fields', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('form_definition_id')->constrained('form_definitions')->cascadeOnDelete();
                $table->string('label');
                $table->string('field_name');
                $table->string('field_type')->default('text'); // text, textarea, number, select, radio, checkbox, date, file
                $table->boolean('is_required')->default(false);
                $table->json('options_json')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['form_definition_id', 'field_name']);
            });
        }

        if (! Schema::hasTable('form_submissions')) {
            Schema::create('form_submissions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('form_definition_id')->constrained('form_definitions')->cascadeOnDelete();
                $table->string('user_id')->nullable()->index(); // user UUID
                $table->string('ip_address', 45)->nullable();
                $table->json('responses_json');
                $table->string('status')->default('submitted')->index(); // submitted, under_review, approved, rejected
                $table->text('reviewer_notes')->nullable();
                $table->string('reviewed_by')->nullable()->index(); // user UUID
                $table->dateTime('reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('poll_votes')) {
            Schema::create('poll_votes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('form_definition_id')->constrained('form_definitions')->cascadeOnDelete();
                $table->string('user_id')->nullable()->index(); // user UUID
                $table->string('ip_address', 45)->nullable();
                $table->string('selected_option');
                $table->timestamps();

                $table->unique(['form_definition_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('form_definitions');
    }
};
