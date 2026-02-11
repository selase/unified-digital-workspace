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
        Schema::create('memos', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id')->index();
            $table->uuid('sender_id')->index();
            $table->string('subject');
            $table->text('body');
            $table->string('status')->default('draft')->index();
            $table->string('signature_disk')->nullable();
            $table->text('signature_path')->nullable();
            $table->string('signature_filename')->nullable();
            $table->string('signature_mime_type')->nullable();
            $table->unsignedBigInteger('signature_size_bytes')->nullable();
            $table->timestampTz('signed_at')->nullable();
            $table->string('verification_code_hash')->nullable();
            $table->timestampTz('verification_sent_at')->nullable();
            $table->timestampTz('verification_expires_at')->nullable();
            $table->unsignedSmallInteger('verification_attempts')->default(0);
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memos');
    }
};
