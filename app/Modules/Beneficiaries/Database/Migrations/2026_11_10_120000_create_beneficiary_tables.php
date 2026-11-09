<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ben_beneficiaries')) {
            Schema::create('ben_beneficiaries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('reference', 32)->nullable();
                $table->string('full_name');
                $table->date('date_of_birth')->nullable();
                $table->string('gender', 16)->nullable();
                $table->string('phone', 64)->nullable();
                $table->string('community')->nullable();
                $table->string('region')->nullable();
                $table->unsignedSmallInteger('household_size')->nullable();
                $table->string('programme')->nullable();
                $table->string('status', 16)->default('active')->index();
                $table->date('enrolled_on')->nullable();
                $table->date('exited_on')->nullable();
                $table->string('exit_reason')->nullable();
                $table->boolean('consent_given')->default(false);
                $table->date('consent_recorded_on')->nullable();
                $table->uuid('consent_recorded_by')->nullable();
                $table->text('consent_scope')->nullable();
                $table->date('consent_withdrawn_on')->nullable();
                $table->timestamp('anonymised_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'reference']);
                $table->index(['tenant_id', 'programme']);
            });
        }

        if (! Schema::hasTable('ben_cases')) {
            Schema::create('ben_cases', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('beneficiary_id')->constrained('ben_beneficiaries')->cascadeOnDelete();
                $table->string('title');
                $table->string('category', 32)->default('general');
                $table->string('priority', 16)->default('normal');
                $table->string('status', 16)->default('open')->index();
                $table->date('opened_on');
                $table->date('closed_on')->nullable();
                $table->string('outcome')->nullable();
                $table->uuid('assigned_to')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ben_case_notes')) {
            Schema::create('ben_case_notes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('case_id')->constrained('ben_cases')->cascadeOnDelete();
                $table->uuid('author_uuid')->nullable();
                $table->text('note');
                $table->boolean('is_confidential')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ben_services')) {
            Schema::create('ben_services', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('beneficiary_id')->constrained('ben_beneficiaries')->cascadeOnDelete();
                $table->date('delivered_on');
                $table->string('type', 32);
                $table->string('description')->nullable();
                $table->bigInteger('value')->nullable();
                $table->uuid('delivered_by')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'delivered_on']);
            });
        }

        if (! Schema::hasTable('ben_access_log')) {
            Schema::create('ben_access_log', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id')->index();
                $table->uuid('user_uuid')->nullable();
                $table->unsignedBigInteger('beneficiary_id')->nullable()->index();
                $table->string('action', 32);
                $table->string('detail')->nullable();
                $table->string('ip', 64)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        foreach (['ben_access_log', 'ben_services', 'ben_case_notes', 'ben_cases', 'ben_beneficiaries'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
