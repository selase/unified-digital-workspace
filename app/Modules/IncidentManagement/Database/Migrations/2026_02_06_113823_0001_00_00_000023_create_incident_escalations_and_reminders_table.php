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
        Schema::create('incident_escalations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('incident_id');
            $table->foreignId('from_priority_id')->nullable()->constrained('incident_priorities')->nullOnDelete();
            $table->foreignId('to_priority_id')->nullable()->constrained('incident_priorities')->nullOnDelete();
            $table->uuid('escalated_by_id')->nullable()->index();
            $table->text('reason')->nullable();
            $table->timestampTz('escalated_at')->nullable();
            $table->timestamps();

            $table->foreign('incident_id')->references('id')->on('incidents')->cascadeOnDelete();
        });

        Schema::create('incident_reminders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('incident_id');
            $table->string('reminder_type');
            $table->timestampTz('scheduled_for')->index();
            $table->timestampTz('sent_at')->nullable();
            $table->string('channel')->default('email');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('incident_id')->references('id')->on('incidents')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_reminders');
        Schema::dropIfExists('incident_escalations');
    }
};
