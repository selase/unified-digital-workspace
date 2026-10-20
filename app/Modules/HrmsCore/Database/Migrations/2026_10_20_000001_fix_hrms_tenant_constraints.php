<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const SLUG_TABLES = [
        'hrms_allowance_types',
        'hrms_allowances',
        'hrms_appraisal_periods',
        'hrms_appraisal_templates',
        'hrms_leave_categories',
        'hrms_salary_levels',
        'hrms_salary_steps',
    ];

    public function up(): void
    {
        if (Schema::hasTable('hrms_employees')) {
            foreach (Schema::getForeignKeys('hrms_employees') as $foreignKey) {
                if ($foreignKey['columns'] === ['user_id']) {
                    Schema::table('hrms_employees', function (Blueprint $table) use ($foreignKey): void {
                        $table->dropForeign($foreignKey['name']);
                    });
                }
            }

            if (! Schema::hasIndex('hrms_employees', ['user_id'])) {
                Schema::table('hrms_employees', function (Blueprint $table): void {
                    $table->index('user_id');
                });
            }
        }

        foreach (self::SLUG_TABLES as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if (! Schema::hasIndex($tableName, ['tenant_id', 'slug'], 'unique')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->unique(['tenant_id', 'slug']);
                });
            }

            foreach (Schema::getIndexes($tableName) as $index) {
                if ($index['unique'] && $index['columns'] === ['slug']) {
                    Schema::table($tableName, function (Blueprint $table) use ($index): void {
                        $table->dropUnique($index['name']);
                    });
                }
            }
        }
    }

    /**
     * Keep tenancy-safe constraints on rollback: global slugs may now overlap,
     * and dedicated databases cannot reference landlord users.
     */
    public function down(): void {}
};
