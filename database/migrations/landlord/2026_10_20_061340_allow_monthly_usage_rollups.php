<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('landlord')->statement('ALTER TABLE usage_rollups DROP CONSTRAINT IF EXISTS usage_rollups_period_check');
        DB::connection('landlord')->statement("ALTER TABLE usage_rollups ADD CONSTRAINT usage_rollups_period_check CHECK (period IN ('minute', 'hour', 'day', 'month'))");
    }

    /**
     * Retain the wider constraint on rollback to preserve monthly billing records.
     */
    public function down(): void {}
};
