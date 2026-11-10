<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('grt_budget_lines')) {
            Schema::create('grt_budget_lines', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id')->index();
                $table->foreignId('grant_id')->constrained('grt_grants')->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('fin_accounts')->restrictOnDelete();
                $table->bigInteger('amount');
                $table->timestamps();

                $table->unique(['grant_id', 'account_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('grt_budget_lines');
    }
};
