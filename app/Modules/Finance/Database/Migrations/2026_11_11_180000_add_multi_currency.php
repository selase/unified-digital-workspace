<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_invoices') && ! Schema::hasColumn('fin_invoices', 'exchange_rate')) {
            Schema::table('fin_invoices', function (Blueprint $table): void {
                $table->decimal('exchange_rate', 16, 6)->default(1);
            });
        }

        if (Schema::hasTable('fin_payments') && ! Schema::hasColumn('fin_payments', 'currency')) {
            Schema::table('fin_payments', function (Blueprint $table): void {
                $table->string('currency', 3)->nullable();
                $table->decimal('exchange_rate', 16, 6)->default(1);
                $table->bigInteger('base_amount')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fin_invoices', 'exchange_rate')) {
            Schema::table('fin_invoices', function (Blueprint $table): void {
                $table->dropColumn('exchange_rate');
            });
        }
        if (Schema::hasColumn('fin_payments', 'currency')) {
            Schema::table('fin_payments', function (Blueprint $table): void {
                $table->dropColumn(['currency', 'exchange_rate', 'base_amount']);
            });
        }
    }
};
