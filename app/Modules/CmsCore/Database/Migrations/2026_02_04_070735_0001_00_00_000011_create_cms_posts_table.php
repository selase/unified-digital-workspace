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
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id')->index();
            $table->foreignId('post_type_id')->constrained('post_types')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('status')->index();
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->timestampTz('published_at')->nullable()->index();
            $table->timestampTz('scheduled_for')->nullable()->index();
            $table->uuid('author_id')->index();
            $table->uuid('editor_id')->nullable()->index();
            $table->foreignId('featured_media_id')->nullable()->index();
            $table->foreignId('parent_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->integer('sort_order')->default(0)->index();
            $table->softDeletesTz();
            $table->timestamps();

            $table->unique(['tenant_id', 'post_type_id', 'slug']);
            $table->index(['tenant_id', 'post_type_id', 'status', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
