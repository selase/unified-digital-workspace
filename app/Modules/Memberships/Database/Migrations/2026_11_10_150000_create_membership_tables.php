<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mem_types')) {
            Schema::create('mem_types', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->bigInteger('fee')->default(0);
                $table->unsignedSmallInteger('period_months')->default(12);
                $table->boolean('is_public')->default(true);
                $table->boolean('auto_invoice')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mem_members')) {
            Schema::create('mem_members', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->string('number', 32);
                $table->foreignId('type_id')->constrained('mem_types')->restrictOnDelete();
                $table->foreignId('contact_id')->nullable()->constrained('fin_contacts')->nullOnDelete();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone', 64)->nullable();
                $table->string('status', 16)->default('pending')->index();
                $table->date('joined_on')->nullable();
                $table->date('expires_on')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'number']);
            });
        }

        if (! Schema::hasTable('mem_renewals')) {
            Schema::create('mem_renewals', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id')->index();
                $table->foreignId('member_id')->constrained('mem_members')->cascadeOnDelete();
                $table->foreignId('type_id')->constrained('mem_types')->restrictOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('fin_invoices')->nullOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->bigInteger('amount')->default(0);
                $table->string('status', 16)->default('billed')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['mem_renewals', 'mem_members', 'mem_types'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
