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
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id')->index();
            $table->string('disk');
            $table->text('path');
            $table->text('original_filename');
            $table->text('filename');
            $table->string('extension')->nullable();
            $table->string('mime_type')->index();
            $table->unsignedBigInteger('size_bytes')->index();
            $table->string('checksum_sha256')->nullable()->index();

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration_seconds', 10, 2)->nullable();
            $table->unsignedInteger('bitrate')->nullable();
            $table->decimal('fps', 6, 2)->nullable();

            $table->string('dominant_color')->nullable();
            $table->string('blurhash')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->text('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->text('title')->nullable();
            $table->text('description')->nullable();

            $table->uuid('uploaded_by')->index();
            $table->string('source')->nullable();
            $table->boolean('is_public')->default(true);
            $table->softDeletesTz();
            $table->timestamps();
        });

        Schema::create('media_post', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['post_id', 'media_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_post');
        Schema::dropIfExists('media');
    }
};
