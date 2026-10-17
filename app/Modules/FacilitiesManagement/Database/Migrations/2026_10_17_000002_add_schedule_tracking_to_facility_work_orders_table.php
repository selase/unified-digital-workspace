<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_work_orders', function (Blueprint $table): void {
            $table->foreignId('preventive_maintenance_schedule_id')
                ->nullable()
                ->after('facility_asset_id')
                ->constrained('preventive_maintenance_schedules')
                ->nullOnDelete();
            $table->date('scheduled_due_date')->nullable()->after('due_date');

            $table->unique(
                ['preventive_maintenance_schedule_id', 'scheduled_due_date'],
                'unique_pm_schedule_due_date'
            );
        });
    }

    public function down(): void
    {
        Schema::table('facility_work_orders', function (Blueprint $table): void {
            $table->dropUnique('unique_pm_schedule_due_date');
            $table->dropConstrainedForeignId('preventive_maintenance_schedule_id');
            $table->dropColumn('scheduled_due_date');
        });
    }
};
