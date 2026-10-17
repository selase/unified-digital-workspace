<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_commission_rate_cards')) {
            Schema::create('vendor_commission_rate_cards', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('category', 64);
                $table->string('label');
                $table->unsignedSmallInteger('tenant_commission_bps')->default(0);
                $table->unsignedSmallInteger('platform_commission_bps')->default(0);
                $table->boolean('is_active')->default(true);
                $table->uuid('approved_by_id')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'category'], 'vendor_rate_cards_tenant_category_unique');
            });
        }

        if (! Schema::hasTable('vendor_profiles')) {
            Schema::create('vendor_profiles', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->uuid('user_id')->nullable()->index();
                $table->string('business_name');
                $table->string('code', 64);
                $table->string('category', 64)->index();
                $table->string('license_number', 120)->nullable();
                $table->string('phone', 64)->nullable();
                $table->string('email')->nullable();
                $table->string('status', 32)->default('verified')->index();
                $table->decimal('rating', 3, 2)->default(5.00);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'code'], 'vendor_profiles_tenant_code_unique');
            });
        }

        if (! Schema::hasTable('vendor_service_jobs')) {
            Schema::create('vendor_service_jobs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('job_number', 64);
                $table->foreignId('vendor_profile_id')->nullable()->constrained('vendor_profiles')->nullOnDelete();
                $table->uuid('requester_id')->index();
                $table->string('source_module', 32)->default('direct')->index();
                $table->unsignedBigInteger('source_reference_id')->nullable();
                $table->string('category', 64)->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('location')->nullable();
                $table->string('status', 32)->default('open')->index();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'job_number'], 'vendor_jobs_tenant_number_unique');
            });
        }

        if (! Schema::hasTable('vendor_proforma_invoices')) {
            Schema::create('vendor_proforma_invoices', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('invoice_number', 64);
                $table->foreignId('service_job_id')->constrained('vendor_service_jobs')->cascadeOnDelete();
                $table->foreignId('vendor_profile_id')->constrained('vendor_profiles')->cascadeOnDelete();
                $table->uuid('client_id')->index();
                $table->string('currency', 8)->default('GHS');
                $table->bigInteger('gross_amount_pesewas');
                $table->unsignedSmallInteger('tenant_commission_bps')->default(0);
                $table->unsignedSmallInteger('platform_commission_bps')->default(0);
                $table->bigInteger('tenant_commission_pesewas')->default(0);
                $table->bigInteger('platform_commission_pesewas')->default(0);
                $table->bigInteger('net_vendor_payout_pesewas')->default(0);
                $table->json('line_items')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 32)->default('submitted')->index();
                $table->date('valid_until')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'invoice_number'], 'vendor_proformas_tenant_number_unique');
            });
        }

        if (! Schema::hasTable('vendor_escrow_accounts')) {
            Schema::create('vendor_escrow_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('escrow_number', 64);
                $table->foreignId('proforma_invoice_id')->constrained('vendor_proforma_invoices')->cascadeOnDelete();
                $table->foreignId('service_job_id')->constrained('vendor_service_jobs')->cascadeOnDelete();
                $table->foreignId('vendor_profile_id')->constrained('vendor_profiles')->cascadeOnDelete();
                $table->uuid('buyer_id')->index();
                $table->string('currency', 8)->default('GHS');
                $table->bigInteger('funded_amount_pesewas')->default(0);
                $table->bigInteger('held_balance_pesewas')->default(0);
                $table->bigInteger('released_vendor_pesewas')->default(0);
                $table->bigInteger('released_tenant_commission_pesewas')->default(0);
                $table->bigInteger('released_platform_commission_pesewas')->default(0);
                $table->bigInteger('refunded_buyer_pesewas')->default(0);
                $table->string('status', 32)->default('pending_funding')->index();
                $table->timestamp('funded_at')->nullable();
                $table->timestamp('settled_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'escrow_number'], 'vendor_escrows_tenant_number_unique');
            });
        }

        if (! Schema::hasTable('vendor_escrow_ledger_entries')) {
            Schema::create('vendor_escrow_ledger_entries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('escrow_account_id')->constrained('vendor_escrow_accounts')->cascadeOnDelete();
                $table->string('idempotency_key', 128);
                $table->string('entry_type', 48)->index();
                $table->string('direction', 16);
                $table->string('beneficiary_type', 32);
                $table->bigInteger('amount_pesewas');
                $table->bigInteger('balance_after_pesewas');
                $table->uuid('actor_id')->nullable();
                $table->string('notes')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->unique(['tenant_id', 'idempotency_key'], 'vendor_escrow_ledger_idempotency_unique');
            });
        }

        if (! Schema::hasTable('vendor_escrow_disputes')) {
            Schema::create('vendor_escrow_disputes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('escrow_account_id')->constrained('vendor_escrow_accounts')->cascadeOnDelete();
                $table->uuid('raised_by_id')->index();
                $table->text('reason');
                $table->string('status', 32)->default('open')->index();
                $table->string('resolution_type', 32)->nullable();
                $table->text('resolution_notes')->nullable();
                $table->uuid('resolved_by_id')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_escrow_disputes');
        Schema::dropIfExists('vendor_escrow_ledger_entries');
        Schema::dropIfExists('vendor_escrow_accounts');
        Schema::dropIfExists('vendor_proforma_invoices');
        Schema::dropIfExists('vendor_service_jobs');
        Schema::dropIfExists('vendor_profiles');
        Schema::dropIfExists('vendor_commission_rate_cards');
    }
};
