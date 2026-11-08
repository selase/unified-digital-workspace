<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_invoices') && ! Schema::hasColumn('fin_invoices', 'credits_applied')) {
            Schema::table('fin_invoices', function (Blueprint $table): void {
                $table->bigInteger('credits_applied')->default(0);
                $table->unsignedBigInteger('credit_for_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fin_invoices', 'credits_applied')) {
            Schema::table('fin_invoices', function (Blueprint $table): void {
                $table->dropColumn(['credits_applied', 'credit_for_id']);
            });
        }
    }
};
