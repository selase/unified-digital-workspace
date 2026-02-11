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
        Schema::create('memo_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('memo_id')->constrained('memos')->cascadeOnDelete();
            $table->uuid('tenant_id')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->uuid('assigned_to_id')->nullable()->index();
            $table->timestampTz('due_at')->nullable();
            $table->string('status')->default('open')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memo_actions');
    }
};
