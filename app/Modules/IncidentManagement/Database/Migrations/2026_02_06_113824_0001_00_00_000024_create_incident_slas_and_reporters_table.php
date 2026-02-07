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
        Schema::create('incident_slas', function (Blueprint $table): void {
            $table->id();
            $table->uuid('incident_id');
            $table->timestampTz('response_due_at')->nullable();
            $table->timestampTz('resolution_due_at')->nullable();
            $table->timestampTz('first_response_at')->nullable();
            $table->timestampTz('resolution_at')->nullable();
            $table->boolean('is_breached')->default(false);
            $table->timestampTz('breached_at')->nullable();
            $table->timestamps();

            $table->foreign('incident_id')->references('id')->on('incidents')->cascadeOnDelete();
        });

        Schema::create('incident_reporters', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('organization')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_reporters');
        Schema::dropIfExists('incident_slas');
    }
};
