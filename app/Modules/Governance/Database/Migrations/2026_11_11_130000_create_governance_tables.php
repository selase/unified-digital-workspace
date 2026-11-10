<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gov_members')) {
            Schema::create('gov_members', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone', 64)->nullable();
                $table->string('position', 64)->default('Member');
                $table->string('body', 64)->default('Board');
                $table->date('term_starts_on')->nullable();
                $table->date('term_ends_on')->nullable()->index();
                $table->string('status', 16)->default('active')->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gov_meetings')) {
            Schema::create('gov_meetings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('title');
                $table->string('body', 64)->default('Board');
                $table->dateTime('meets_at')->index();
                $table->string('venue')->nullable();
                $table->string('status', 16)->default('scheduled')->index();
                $table->unsignedSmallInteger('quorum')->nullable();
                $table->text('agenda')->nullable();
                $table->longText('minutes')->nullable();
                $table->string('minutes_status', 16)->default('none');
                $table->date('minutes_approved_on')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gov_attendance')) {
            Schema::create('gov_attendance', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('meeting_id')->constrained('gov_meetings')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('gov_members')->cascadeOnDelete();
                $table->string('status', 16)->default('present');
                $table->timestamps();

                $table->unique(['meeting_id', 'member_id']);
            });
        }

        if (! Schema::hasTable('gov_resolutions')) {
            Schema::create('gov_resolutions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('meeting_id')->constrained('gov_meetings')->cascadeOnDelete();
                $table->string('reference', 32);
                $table->text('text');
                $table->string('outcome', 16)->default('passed');
                $table->timestamps();

                $table->unique(['tenant_id', 'reference']);
            });
        }

        if (! Schema::hasTable('gov_actions')) {
            Schema::create('gov_actions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('meeting_id')->nullable()->constrained('gov_meetings')->nullOnDelete();
                $table->foreignId('member_id')->nullable()->constrained('gov_members')->nullOnDelete();
                $table->string('description');
                $table->string('owner_name')->nullable();
                $table->date('due_on')->nullable()->index();
                $table->string('status', 16)->default('open')->index();
                $table->date('completed_on')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['gov_actions', 'gov_resolutions', 'gov_attendance', 'gov_meetings', 'gov_members'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
