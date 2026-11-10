<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_settings') && ! Schema::hasColumn('fin_settings', 'evat_enabled')) {
            Schema::table('fin_settings', function (Blueprint $table): void {
                $table->boolean('evat_enabled')->default(false);
            });
        }

        if (Schema::hasTable('fin_invoices') && ! Schema::hasColumn('fin_invoices', 'evat_status')) {
            Schema::table('fin_invoices', function (Blueprint $table): void {
                $table->string('evat_status', 16)->default('not_required');
                $table->string('evat_reference')->nullable();
                $table->text('evat_message')->nullable();
                $table->timestamp('evat_submitted_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fin_invoices', 'evat_status')) {
            Schema::table('fin_invoices', function (Blueprint $table): void {
                $table->dropColumn(['evat_status', 'evat_reference', 'evat_message', 'evat_submitted_at']);
            });
        }
        if (Schema::hasColumn('fin_settings', 'evat_enabled')) {
            Schema::table('fin_settings', function (Blueprint $table): void {
                $table->dropColumn('evat_enabled');
            });
        }
    }
};
