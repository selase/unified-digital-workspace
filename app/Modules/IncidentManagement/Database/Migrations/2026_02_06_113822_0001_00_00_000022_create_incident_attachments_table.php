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
        Schema::create('incident_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('incident_id');
            $table->foreignId('comment_id')->nullable()->constrained('incident_comments')->nullOnDelete();
            $table->string('disk');
            $table->text('path');
            $table->text('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->uuid('uploaded_by_id')->index();
            $table->timestamps();

            $table->foreign('incident_id')->references('id')->on('incidents')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_attachments');
    }
};
