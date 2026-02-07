<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_progress_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('incident_id');
            $table->uuid('user_id')->index();
            $table->text('body');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();

            $table->foreign('incident_id')->references('id')->on('incidents')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_progress_reports');
    }
};
