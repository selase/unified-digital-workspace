<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('status')->default('planned');
            $table->string('priority')->default('medium');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->decimal('budget_amount', 14, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->uuid('owner_id')->nullable()->index();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'owner_id']);
        });

        Schema::create('project_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->uuid('user_id')->index();
            $table->string('role')->nullable();
            $table->timestampTz('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('milestones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id')->index();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained('milestones')->nullOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('status')->default('todo');
            $table->string('priority')->default('medium');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->timestampTz('completed_at')->nullable();
            $table->integer('estimated_minutes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'milestone_id']);
        });

        Schema::create('task_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'depends_on_task_id']);
        });

        Schema::create('task_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->uuid('user_id')->index();
            $table->uuid('assigned_by_id')->nullable()->index();
            $table->timestampTz('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
        });

        Schema::create('task_comments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->uuid('user_id')->index();
            $table->longText('body');
            $table->timestamps();
        });

        Schema::create('task_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->uuid('uploaded_by_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('time_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->uuid('user_id')->index();
            $table->date('entry_date');
            $table->unsignedInteger('minutes');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'entry_date']);
        });

        Schema::create('resource_allocations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->uuid('user_id')->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedTinyInteger('allocation_percent');
            $table->string('role')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_allocations');
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_assignments');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
