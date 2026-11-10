<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_settlements')) {
            Schema::create('fin_settlements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('gateway', 24);
                $table->string('external_id', 64);
                $table->date('settled_on');
                $table->bigInteger('gross');
                $table->bigInteger('fees');
                $table->bigInteger('net');
                $table->string('status', 16)->default('imported');
                $table->foreignId('journal_id')->nullable()->constrained('fin_journals')->nullOnDelete();
                $table->timestamps();

                $table->unique(['tenant_id', 'gateway', 'external_id']);
            });
        }

        if (! Schema::hasTable('fin_attachments')) {
            Schema::create('fin_attachments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('attachable_type', 16);
                $table->unsignedBigInteger('attachable_id');
                $table->string('name');
                $table->string('path');
                $table->string('mime', 100);
                $table->unsignedInteger('size');
                $table->uuid('uploaded_by')->nullable();
                $table->timestamps();

                $table->index(['attachable_type', 'attachable_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_attachments');
        Schema::dropIfExists('fin_settlements');
    }
};
