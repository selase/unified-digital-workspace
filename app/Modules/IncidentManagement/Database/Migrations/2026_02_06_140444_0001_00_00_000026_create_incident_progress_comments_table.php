<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_progress_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('progress_report_id')->constrained('incident_progress_reports')->cascadeOnDelete();
            $table->uuid('user_id')->index();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_progress_comments');
    }
};
