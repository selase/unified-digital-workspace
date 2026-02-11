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
        Schema::create('memo_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('memo_id')->constrained('memos')->cascadeOnDelete();
            $table->uuid('tenant_id')->index();
            $table->string('recipient_type')->index();
            $table->string('recipient_id')->nullable()->index();
            $table->string('role')->default('to');
            $table->boolean('requires_ack')->default(false);
            $table->timestampTz('acknowledged_at')->nullable();
            $table->uuid('acknowledged_by_id')->nullable()->index();
            $table->uuid('shared_by_id')->nullable()->index();
            $table->timestampTz('shared_at')->nullable();
            $table->timestamps();

            $table->unique(['memo_id', 'recipient_type', 'recipient_id', 'role'], 'memo_recipients_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memo_recipients');
    }
};
