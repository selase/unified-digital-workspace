<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ben_documents')) {
            Schema::create('ben_documents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('beneficiary_id')->constrained('ben_beneficiaries')->cascadeOnDelete();
                $table->string('kind', 24)->default('other');
                $table->string('name');
                $table->string('path');
                $table->string('mime', 64);
                $table->unsignedInteger('size');
                $table->uuid('uploaded_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ben_referrals')) {
            Schema::create('ben_referrals', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('beneficiary_id')->constrained('ben_beneficiaries')->cascadeOnDelete();
                $table->foreignId('case_id')->nullable()->constrained('ben_cases')->nullOnDelete();
                $table->string('referred_to');
                $table->string('reason')->nullable();
                $table->date('referred_on');
                $table->date('follow_up_on')->nullable()->index();
                $table->string('status', 16)->default('made')->index();
                $table->string('outcome')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ben_referrals');
        Schema::dropIfExists('ben_documents');
    }
};
