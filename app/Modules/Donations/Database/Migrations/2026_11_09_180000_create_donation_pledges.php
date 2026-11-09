<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('don_pledges')) {
            Schema::create('don_pledges', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('donor_id')->constrained('don_donors')->restrictOnDelete();
                $table->foreignId('campaign_id')->nullable()->constrained('don_campaigns')->nullOnDelete();
                $table->foreignId('fund_id')->nullable()->constrained('fin_funds')->nullOnDelete();
                $table->bigInteger('amount');
                $table->string('frequency', 16)->default('monthly');
                $table->date('starts_on');
                $table->date('next_due_on')->nullable()->index();
                $table->date('ends_on')->nullable();
                $table->string('status', 16)->default('active')->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('don_donations') && ! Schema::hasColumn('don_donations', 'pledge_id')) {
            Schema::table('don_donations', function (Blueprint $table): void {
                $table->foreignId('pledge_id')->nullable()->after('campaign_id')->constrained('don_pledges')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('don_donations', 'pledge_id')) {
            Schema::table('don_donations', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('pledge_id');
            });
        }
        Schema::dropIfExists('don_pledges');
    }
};
