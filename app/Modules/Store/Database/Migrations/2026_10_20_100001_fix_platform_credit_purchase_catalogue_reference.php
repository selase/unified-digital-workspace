<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') || ! Schema::hasTable('platform_credit_purchases')) {
            return;
        }
        foreach (Schema::getForeignKeys('platform_credit_purchases') as $foreignKey) {
            if ($foreignKey['columns'] === ['package_id']) {
                Schema::table('platform_credit_purchases', function (Blueprint $table) use ($foreignKey): void {
                    $table->dropForeign($foreignKey['name']);
                });
            }
        }
        if (! Schema::hasIndex('platform_credit_purchases', ['package_id'])) {
            Schema::table('platform_credit_purchases', function (Blueprint $table): void {
                $table->index('package_id');
            });
        }
    }

    /**
     * Dedicated purchases cannot safely restore a foreign key to a local catalogue.
     */
    public function down(): void {}
};
