<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * All amounts are integer minor units (pesewas, cents) in the tenant's base currency.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_settings')) {
            Schema::create('fin_settings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id')->unique();
                $table->string('base_currency', 3)->default('GHS');
                $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);
                $table->date('lock_date')->nullable();
                $table->boolean('tax_enabled')->default(false);
                $table->string('legal_name')->nullable();
                $table->string('tax_number', 64)->nullable();
                $table->text('address')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 64)->nullable();
                $table->unsignedSmallInteger('default_payment_terms_days')->default(14);
                $table->text('invoice_footer')->nullable();
                $table->json('prefixes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fin_accounts')) {
            Schema::create('fin_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('code', 32);
                $table->string('name');
                $table->string('type', 16);
                $table->string('system_key', 48)->nullable();
                $table->foreignId('parent_id')->nullable()->constrained('fin_accounts')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
                $table->index(['tenant_id', 'system_key']);
            });
        }

        if (! Schema::hasTable('fin_funds')) {
            Schema::create('fin_funds', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('code', 32);
                $table->string('name');
                $table->boolean('is_restricted')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
            });
        }

        if (! Schema::hasTable('fin_journals')) {
            Schema::create('fin_journals', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('number', 32);
                $table->date('date')->index();
                $table->string('memo')->nullable();
                $table->string('status', 16)->default('posted');
                $table->string('source_type', 32)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->foreignId('reverses_journal_id')->nullable()->constrained('fin_journals')->nullOnDelete();
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'number']);
                $table->index(['tenant_id', 'source_type', 'source_id']);
            });
        }

        if (! Schema::hasTable('fin_contacts')) {
            Schema::create('fin_contacts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->string('company')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 64)->nullable();
                $table->text('address')->nullable();
                $table->string('tax_number', 64)->nullable();
                $table->boolean('is_customer')->default(true);
                $table->boolean('is_vendor')->default(false);
                $table->unsignedSmallInteger('payment_terms_days')->nullable();
                $table->unsignedBigInteger('crm_account_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('fin_items')) {
            Schema::create('fin_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->string('sku', 64)->nullable();
                $table->text('description')->nullable();
                $table->bigInteger('unit_price')->default(0);
                $table->foreignId('income_account_id')->nullable()->constrained('fin_accounts')->nullOnDelete();
                $table->foreignId('tax_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fin_taxes')) {
            Schema::create('fin_taxes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->json('components');
                $table->unsignedInteger('rate_basis_points')->default(0);
                $table->boolean('is_active')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fin_invoices')) {
            Schema::create('fin_invoices', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('type', 16)->default('invoice');
                $table->string('number', 32)->nullable();
                $table->foreignId('contact_id')->constrained('fin_contacts')->restrictOnDelete();
                $table->string('status', 16)->default('draft')->index();
                $table->date('issue_date');
                $table->date('due_date')->nullable();
                $table->string('currency', 3)->default('GHS');
                $table->bigInteger('subtotal')->default(0);
                $table->bigInteger('tax_total')->default(0);
                $table->bigInteger('total')->default(0);
                $table->bigInteger('amount_paid')->default(0);
                $table->text('notes')->nullable();
                $table->text('terms')->nullable();
                $table->foreignId('journal_id')->nullable()->constrained('fin_journals')->nullOnDelete();
                $table->foreignId('converted_invoice_id')->nullable();
                $table->foreignId('recurring_id')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'type', 'number']);
                $table->index(['tenant_id', 'status', 'due_date']);
            });
        }

        if (! Schema::hasTable('fin_invoice_lines')) {
            Schema::create('fin_invoice_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('invoice_id')->constrained('fin_invoices')->cascadeOnDelete();
                $table->unsignedSmallInteger('position')->default(0);
                $table->foreignId('item_id')->nullable()->constrained('fin_items')->nullOnDelete();
                $table->string('description');
                $table->unsignedInteger('quantity_thousandths')->default(1000);
                $table->bigInteger('unit_price')->default(0);
                $table->foreignId('account_id')->nullable()->constrained('fin_accounts')->nullOnDelete();
                $table->foreignId('tax_id')->nullable()->constrained('fin_taxes')->nullOnDelete();
                $table->foreignId('fund_id')->nullable()->constrained('fin_funds')->nullOnDelete();
                $table->bigInteger('line_total')->default(0);
                $table->bigInteger('tax_amount')->default(0);
            });
        }

        if (! Schema::hasTable('fin_journal_lines')) {
            Schema::create('fin_journal_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('journal_id')->constrained('fin_journals')->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('fin_accounts')->restrictOnDelete();
                $table->bigInteger('debit')->default(0);
                $table->bigInteger('credit')->default(0);
                $table->string('memo')->nullable();
                $table->foreignId('contact_id')->nullable()->constrained('fin_contacts')->nullOnDelete();
                $table->foreignId('fund_id')->nullable()->constrained('fin_funds')->nullOnDelete();

                $table->index('account_id');
            });
        }

        if (! Schema::hasTable('fin_payments')) {
            Schema::create('fin_payments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('number', 32);
                $table->foreignId('contact_id')->constrained('fin_contacts')->restrictOnDelete();
                $table->date('date');
                $table->bigInteger('amount');
                $table->string('method', 24)->default('bank_transfer');
                $table->string('reference')->nullable();
                $table->string('gateway', 24)->nullable();
                $table->string('gateway_reference')->nullable();
                $table->foreignId('deposit_account_id')->nullable()->constrained('fin_accounts')->nullOnDelete();
                $table->foreignId('journal_id')->nullable()->constrained('fin_journals')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'number']);
                $table->unique(['tenant_id', 'gateway', 'gateway_reference']);
            });
        }

        if (! Schema::hasTable('fin_payment_allocations')) {
            Schema::create('fin_payment_allocations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payment_id')->constrained('fin_payments')->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained('fin_invoices')->restrictOnDelete();
                $table->bigInteger('amount');
            });
        }

        if (! Schema::hasTable('fin_recurring_invoices')) {
            Schema::create('fin_recurring_invoices', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('contact_id')->constrained('fin_contacts')->restrictOnDelete();
                $table->string('name');
                $table->string('frequency', 16)->default('monthly');
                $table->date('next_run_date')->index();
                $table->date('end_date')->nullable();
                $table->boolean('auto_send')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('lines');
                $table->text('notes')->nullable();
                $table->unsignedSmallInteger('terms_days')->default(14);
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fin_sequences')) {
            Schema::create('fin_sequences', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->string('key', 32);
                $table->unsignedBigInteger('last_number')->default(0);

                $table->unique(['tenant_id', 'key']);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'fin_sequences', 'fin_recurring_invoices', 'fin_payment_allocations', 'fin_payments',
            'fin_journal_lines', 'fin_invoice_lines', 'fin_invoices', 'fin_taxes', 'fin_items',
            'fin_contacts', 'fin_journals', 'fin_funds', 'fin_accounts', 'fin_settings',
        ] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
