<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('grt_funders')) {
            Schema::create('grt_funders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->string('type', 24)->default('foundation');
                $table->string('contact_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 64)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('grt_grants')) {
            Schema::create('grt_grants', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('funder_id')->constrained('grt_funders')->restrictOnDelete();
                $table->foreignId('fund_id')->nullable()->constrained('fin_funds')->nullOnDelete();
                $table->string('title');
                $table->string('reference')->nullable();
                $table->bigInteger('total_amount');
                $table->string('currency', 3)->default('GHS');
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->string('status', 16)->default('pipeline')->index();
                $table->text('purpose')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('grt_instalments')) {
            Schema::create('grt_instalments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('grant_id')->constrained('grt_grants')->cascadeOnDelete();
                $table->date('expected_on');
                $table->bigInteger('amount');
                $table->string('status', 16)->default('expected');
                $table->date('received_on')->nullable();
                $table->string('reference')->nullable();
                $table->foreignId('deposit_account_id')->nullable()->constrained('fin_accounts')->nullOnDelete();
                $table->foreignId('journal_id')->nullable()->constrained('fin_journals')->nullOnDelete();
                $table->uuid('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('grt_reports')) {
            Schema::create('grt_reports', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('grant_id')->constrained('grt_grants')->cascadeOnDelete();
                $table->string('title');
                $table->date('due_on')->index();
                $table->string('status', 16)->default('pending');
                $table->date('submitted_on')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['grt_reports', 'grt_instalments', 'grt_grants', 'grt_funders'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
