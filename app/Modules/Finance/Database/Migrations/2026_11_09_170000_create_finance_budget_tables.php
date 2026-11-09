<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_budgets')) {
            Schema::create('fin_budgets', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->date('starts_on');
                $table->date('ends_on');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fin_budget_lines')) {
            Schema::create('fin_budget_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('budget_id')->constrained('fin_budgets')->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('fin_accounts')->restrictOnDelete();
                $table->foreignId('fund_id')->nullable()->constrained('fin_funds')->nullOnDelete();
                $table->bigInteger('amount');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_budget_lines');
        Schema::dropIfExists('fin_budgets');
    }
};
