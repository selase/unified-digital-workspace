<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vol_volunteers')) {
            Schema::create('vol_volunteers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone', 64)->nullable();
                $table->text('skills')->nullable();
                $table->string('status', 16)->default('pending')->index();
                $table->date('joined_on')->nullable();
                $table->string('emergency_contact')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('vol_opportunities')) {
            Schema::create('vol_opportunities', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('location')->nullable();
                $table->date('starts_on');
                $table->unsignedSmallInteger('spots')->default(0);
                $table->string('status', 16)->default('open');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vol_signups')) {
            Schema::create('vol_signups', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('opportunity_id')->constrained('vol_opportunities')->cascadeOnDelete();
                $table->foreignId('volunteer_id')->constrained('vol_volunteers')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['opportunity_id', 'volunteer_id']);
            });
        }

        if (! Schema::hasTable('vol_hours')) {
            Schema::create('vol_hours', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('volunteer_id')->constrained('vol_volunteers')->cascadeOnDelete();
                $table->foreignId('opportunity_id')->nullable()->constrained('vol_opportunities')->nullOnDelete();
                $table->date('worked_on');
                $table->unsignedSmallInteger('minutes');
                $table->string('activity')->nullable();
                $table->string('status', 16)->default('pending')->index();
                $table->uuid('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['vol_hours', 'vol_signups', 'vol_opportunities', 'vol_volunteers'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
