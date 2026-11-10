<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mel_indicators')) {
            Schema::create('mel_indicators', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('programme')->nullable()->index();
                $table->string('unit', 32)->default('people');
                $table->string('source', 32)->default('manual');
                $table->decimal('baseline', 14, 2)->default(0);
                $table->decimal('target', 14, 2);
                $table->date('starts_on');
                $table->date('ends_on');
                $table->unsignedBigInteger('grant_id')->nullable()->index();
                $table->string('status', 16)->default('active')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mel_readings')) {
            Schema::create('mel_readings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id')->index();
                $table->foreignId('indicator_id')->constrained('mel_indicators')->cascadeOnDelete();
                $table->date('recorded_on');
                $table->decimal('value', 14, 2);
                $table->string('note')->nullable();
                $table->uuid('recorded_by')->nullable();
                $table->timestamps();

                $table->index(['indicator_id', 'recorded_on']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mel_readings');
        Schema::dropIfExists('mel_indicators');
    }
};
