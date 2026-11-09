<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_bills')) {
            Schema::create('fin_bills', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('number', 32)->nullable();
                $table->string('vendor_reference')->nullable();
                $table->foreignId('contact_id')->constrained('fin_contacts')->restrictOnDelete();
                $table->string('status', 16)->default('draft')->index();
                $table->date('issue_date');
                $table->date('due_date')->nullable();
                $table->string('currency', 3)->default('GHS');
                $table->bigInteger('total')->default(0);
                $table->bigInteger('amount_paid')->default(0);
                $table->text('notes')->nullable();
                $table->foreignId('journal_id')->nullable()->constrained('fin_journals')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'number']);
            });
        }

        if (! Schema::hasTable('fin_bill_lines')) {
            Schema::create('fin_bill_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('bill_id')->constrained('fin_bills')->cascadeOnDelete();
                $table->unsignedSmallInteger('position')->default(0);
                $table->string('description');
                $table->bigInteger('amount');
                $table->foreignId('account_id')->constrained('fin_accounts')->restrictOnDelete();
                $table->foreignId('fund_id')->nullable()->constrained('fin_funds')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('fin_bill_payments')) {
            Schema::create('fin_bill_payments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('number', 32);
                $table->foreignId('bill_id')->constrained('fin_bills')->restrictOnDelete();
                $table->foreignId('contact_id')->constrained('fin_contacts')->restrictOnDelete();
                $table->date('date');
                $table->bigInteger('amount');
                $table->string('method', 24)->default('bank_transfer');
                $table->string('reference')->nullable();
                $table->foreignId('paid_from_account_id')->constrained('fin_accounts')->restrictOnDelete();
                $table->foreignId('journal_id')->nullable()->constrained('fin_journals')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'number']);
            });
        }

        if (! Schema::hasTable('fin_expenses')) {
            Schema::create('fin_expenses', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('number', 32);
                $table->foreignId('contact_id')->nullable()->constrained('fin_contacts')->nullOnDelete();
                $table->date('date');
                $table->foreignId('account_id')->constrained('fin_accounts')->restrictOnDelete();
                $table->foreignId('paid_from_account_id')->constrained('fin_accounts')->restrictOnDelete();
                $table->foreignId('fund_id')->nullable()->constrained('fin_funds')->nullOnDelete();
                $table->bigInteger('amount');
                $table->string('description')->nullable();
                $table->string('reference')->nullable();
                $table->foreignId('journal_id')->nullable()->constrained('fin_journals')->nullOnDelete();
                $table->timestamp('voided_at')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'number']);
            });
        }
    }

    public function down(): void
    {
        foreach (['fin_expenses', 'fin_bill_payments', 'fin_bill_lines', 'fin_bills'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
