<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id')->index();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->jsonb('visibility')->nullable();
            $table->foreignId('current_version_id')->nullable()->index();
            $table->uuid('owner_id')->index();
            $table->string('category')->nullable();
            $table->jsonb('tags')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('published_at')->nullable()->index();
            $table->softDeletesTz();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status', 'published_at']);
        });

        Schema::create('document_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('disk');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum_sha256')->nullable();
            $table->uuid('uploaded_by_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
        });

        Schema::create('document_quizzes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('document_quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('quiz_id')->constrained('document_quizzes')->cascadeOnDelete();
            $table->text('body');
            $table->jsonb('options');
            $table->string('correct_option')->nullable();
            $table->unsignedInteger('points')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('document_quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('quiz_id')->constrained('document_quizzes')->cascadeOnDelete();
            $table->uuid('user_id')->index();
            $table->unsignedInteger('score')->nullable();
            $table->jsonb('responses');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_audits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->uuid('user_id')->nullable()->index();
            $table->string('event');
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_audits');
        Schema::dropIfExists('document_quiz_attempts');
        Schema::dropIfExists('document_quiz_questions');
        Schema::dropIfExists('document_quizzes');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};
