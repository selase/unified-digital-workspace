<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_channels', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->jsonb('visibility')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'sort_order']);
        });

        Schema::create('forum_threads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id')->index();
            $table->foreignId('channel_id')->constrained('forum_channels')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->uuid('user_id')->index();
            $table->string('status')->default('open')->index();
            $table->timestampTz('pinned_at')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->jsonb('tags')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'slug']);
            $table->index(['channel_id', 'status', 'pinned_at']);
        });

        Schema::create('forum_posts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('thread_id')->constrained('forum_threads')->cascadeOnDelete();
            $table->uuid('user_id')->index();
            $table->foreignId('parent_id')->nullable()->constrained('forum_posts')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_best_answer')->default(false);
            $table->timestampTz('edited_at')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'parent_id']);
        });

        Schema::create('forum_reactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('post_id')->constrained('forum_posts')->cascadeOnDelete();
            $table->uuid('user_id')->index();
            $table->string('type');
            $table->timestamps();

            $table->unique(['post_id', 'user_id', 'type']);
            $table->index(['post_id', 'type']);
        });

        Schema::create('forum_moderation_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('thread_id')->nullable()->constrained('forum_threads')->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained('forum_posts')->cascadeOnDelete();
            $table->uuid('moderator_id')->index();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('forum_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id')->index();
            $table->uuid('sender_id')->index();
            $table->string('subject');
            $table->text('body');
            $table->jsonb('visibility')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'sender_id']);
        });

        Schema::create('forum_message_recipients', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('message_id')->constrained('forum_messages')->cascadeOnDelete();
            $table->uuid('user_id')->index();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
            $table->index(['message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_message_recipients');
        Schema::dropIfExists('forum_messages');
        Schema::dropIfExists('forum_moderation_logs');
        Schema::dropIfExists('forum_reactions');
        Schema::dropIfExists('forum_posts');
        Schema::dropIfExists('forum_threads');
        Schema::dropIfExists('forum_channels');
    }
};
