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
        Schema::create('incident_tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('incident_id');
            $table->uuid('assigned_to_id')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('ongoing')->index();
            $table->timestampTz('due_at')->nullable()->index();
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('incident_id')->references('id')->on('incidents')->cascadeOnDelete();
        });

        Schema::create('incident_comments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('incident_id');
            $table->uuid('user_id')->index();
            $table->text('body');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();

            $table->foreign('incident_id')->references('id')->on('incidents')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_comments');
        Schema::dropIfExists('incident_tasks');
    }
};
