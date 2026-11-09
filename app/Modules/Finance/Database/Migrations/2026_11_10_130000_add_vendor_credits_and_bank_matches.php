<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_bills') && ! Schema::hasColumn('fin_bills', 'credit_for_id')) {
            Schema::table('fin_bills', function (Blueprint $table): void {
                $table->string('type', 12)->default('bill')->after('uuid');
                $table->foreignId('credit_for_id')->nullable()->constrained('fin_bills')->nullOnDelete();
                $table->bigInteger('credits_applied')->default(0);
            });
        }

        if (! Schema::hasTable('fin_bank_matches')) {
            Schema::create('fin_bank_matches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('transaction_id')->constrained('fin_bank_transactions')->cascadeOnDelete();
                $table->foreignId('journal_line_id')->unique()->constrained('fin_journal_lines')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_bank_matches');

        if (Schema::hasColumn('fin_bills', 'credit_for_id')) {
            Schema::table('fin_bills', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('credit_for_id');
                $table->dropColumn(['type', 'credits_applied']);
            });
        }
    }
};
