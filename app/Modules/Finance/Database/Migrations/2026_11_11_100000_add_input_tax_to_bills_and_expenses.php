<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_taxes') && ! Schema::hasColumn('fin_taxes', 'category')) {
            Schema::table('fin_taxes', function (Blueprint $table): void {
                $table->string('category', 16)->default('standard');
            });
        }

        if (Schema::hasTable('fin_bills') && ! Schema::hasColumn('fin_bills', 'tax_total')) {
            Schema::table('fin_bills', function (Blueprint $table): void {
                $table->bigInteger('subtotal')->default(0);
                $table->bigInteger('tax_total')->default(0);
            });
            DB::table('fin_bills')->update(['subtotal' => DB::raw('total')]);
        }

        if (Schema::hasTable('fin_bill_lines') && ! Schema::hasColumn('fin_bill_lines', 'tax_id')) {
            Schema::table('fin_bill_lines', function (Blueprint $table): void {
                $table->foreignId('tax_id')->nullable()->constrained('fin_taxes')->nullOnDelete();
                $table->bigInteger('tax_amount')->default(0);
            });
        }

        if (Schema::hasTable('fin_expenses') && ! Schema::hasColumn('fin_expenses', 'tax_id')) {
            Schema::table('fin_expenses', function (Blueprint $table): void {
                $table->foreignId('tax_id')->nullable()->constrained('fin_taxes')->nullOnDelete();
                $table->bigInteger('tax_amount')->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach ([['fin_expenses', ['tax_id', 'tax_amount']], ['fin_bill_lines', ['tax_id', 'tax_amount']], ['fin_bills', ['subtotal', 'tax_total']], ['fin_taxes', ['category']]] as [$table, $columns]) {
            if (Schema::hasColumn($table, $columns[0])) {
                Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                    foreach ($columns as $column) {
                        if (str_ends_with($column, '_id')) {
                            $blueprint->dropConstrainedForeignId($column);
                        } else {
                            $blueprint->dropColumn($column);
                        }
                    }
                });
            }
        }
    }
};
