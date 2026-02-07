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
        Schema::create('incident_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('incident_id');
            $table->uuid('assigned_to_id')->index();
            $table->uuid('assigned_by_id')->nullable()->index();
            $table->uuid('delegated_from_id')->nullable()->index();
            $table->timestampTz('assigned_at')->nullable();
            $table->timestampTz('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('incident_id')->references('id')->on('incidents')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_assignments');
    }
};
