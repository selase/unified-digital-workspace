<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_accounts')) {
            Schema::create('crm_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->string('code', 64);
                $table->string('account_type', 32)->default('corporate');
                $table->string('industry')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 64)->nullable();
                $table->string('address')->nullable();
                $table->uuid('owner_id')->nullable()->index();
                $table->uuid('portal_user_id')->nullable()->index();
                $table->string('status', 32)->default('active')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'code'], 'crm_accounts_tenant_code_unique');
            });
        }

        if (! Schema::hasTable('crm_contacts')) {
            Schema::create('crm_contacts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('account_id')->constrained('crm_accounts')->cascadeOnDelete();
                $table->string('first_name');
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 64)->nullable();
                $table->string('job_title')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->uuid('portal_user_id')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('crm_leads')) {
            Schema::create('crm_leads', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('reference_code', 64);
                $table->string('source', 32)->default('walk_in')->index();
                $table->string('enquiry_type', 64)->nullable();
                $table->string('full_name');
                $table->string('email')->nullable();
                $table->string('phone', 64)->nullable();
                $table->string('organisation')->nullable();
                $table->unsignedBigInteger('interested_listing_id')->nullable()->index();
                $table->text('message')->nullable();
                $table->string('status', 32)->default('new')->index();
                $table->uuid('assigned_to_id')->nullable()->index();
                $table->foreignId('converted_account_id')->nullable()->constrained('crm_accounts')->nullOnDelete();
                $table->foreignId('converted_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'reference_code'], 'crm_leads_tenant_ref_unique');
            });
        }

        if (! Schema::hasTable('crm_deals')) {
            Schema::create('crm_deals', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('account_id')->constrained('crm_accounts')->cascadeOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
                $table->string('title');
                $table->string('stage', 32)->default('discovery')->index();
                $table->string('currency', 8)->default('GHS');
                $table->bigInteger('value_pesewas')->default(0);
                $table->unsignedTinyInteger('probability')->default(25);
                $table->date('expected_close_date')->nullable();
                $table->uuid('owner_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('crm_activities')) {
            Schema::create('crm_activities', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('subject_type');
                $table->unsignedBigInteger('subject_id');
                $table->string('activity_type', 32)->default('note')->index();
                $table->string('summary');
                $table->text('details')->nullable();
                $table->uuid('logged_by_id')->nullable()->index();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->index(['tenant_id', 'subject_type', 'subject_id'], 'crm_activities_subject_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_deals');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_accounts');
    }
};
