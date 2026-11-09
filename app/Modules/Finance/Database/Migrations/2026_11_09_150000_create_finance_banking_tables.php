<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_bank_accounts')) {
            Schema::create('fin_bank_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->foreignId('account_id')->constrained('fin_accounts')->restrictOnDelete();
                $table->string('institution')->nullable();
                $table->string('account_reference', 64)->nullable();
                $table->bigInteger('opening_balance')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'account_id']);
            });
        }

        if (! Schema::hasTable('fin_bank_transactions')) {
            Schema::create('fin_bank_transactions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('bank_account_id')->constrained('fin_bank_accounts')->cascadeOnDelete();
                $table->date('date');
                $table->string('description')->nullable();
                $table->string('reference')->nullable();
                $table->bigInteger('amount');
                $table->string('status', 16)->default('unmatched');
                $table->foreignId('journal_line_id')->nullable()->unique()->constrained('fin_journal_lines')->nullOnDelete();
                $table->string('fingerprint', 64);
                $table->timestamp('matched_at')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->unique(['bank_account_id', 'fingerprint']);
                $table->index(['bank_account_id', 'status', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_bank_transactions');
        Schema::dropIfExists('fin_bank_accounts');
    }
};
