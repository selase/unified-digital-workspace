<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('don_donors')) {
            Schema::create('don_donors', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->string('type', 16)->default('individual');
                $table->string('email')->nullable();
                $table->string('phone', 64)->nullable();
                $table->text('address')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'name']);
            });
        }

        if (! Schema::hasTable('don_campaigns')) {
            Schema::create('don_campaigns', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->bigInteger('goal_amount')->default(0);
                $table->foreignId('fund_id')->nullable()->constrained('fin_funds')->nullOnDelete();
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('don_donations')) {
            Schema::create('don_donations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('number', 32);
                $table->string('receipt_number', 32);
                $table->foreignId('donor_id')->constrained('don_donors')->restrictOnDelete();
                $table->foreignId('campaign_id')->nullable()->constrained('don_campaigns')->nullOnDelete();
                $table->foreignId('fund_id')->nullable()->constrained('fin_funds')->nullOnDelete();
                $table->date('date');
                $table->bigInteger('amount');
                $table->string('currency', 3)->default('GHS');
                $table->string('method', 24)->default('bank_transfer');
                $table->string('reference')->nullable();
                $table->foreignId('deposit_account_id')->constrained('fin_accounts')->restrictOnDelete();
                $table->foreignId('journal_id')->nullable()->constrained('fin_journals')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamp('receipt_sent_at')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'number']);
                $table->unique(['tenant_id', 'receipt_number']);
                $table->index(['tenant_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        foreach (['don_donations', 'don_campaigns', 'don_donors'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
