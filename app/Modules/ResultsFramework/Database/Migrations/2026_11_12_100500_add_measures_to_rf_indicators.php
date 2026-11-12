<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SOURCES = [
        'beneficiaries_reached' => 'beneficiaries.people_reached',
        'volunteer_hours' => 'volunteers.hours_approved',
        'donations_total' => 'donations.amount_raised',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('rf_indicators', 'source')) {
            return;
        }

        if (DB::table('rf_indicators')->whereNotIn('source', ['manual', ...array_keys(self::SOURCES)])->exists()) {
            throw new RuntimeException('An indicator uses an unrecognised legacy source.');
        }

        Schema::table('rf_indicators', function (Blueprint $table): void {
            $table->string('measure_key')->nullable();
            $table->json('measure_filters')->nullable();
        });

        foreach (self::SOURCES as $source => $measure) {
            DB::table('rf_indicators')->where('source', $source)->update(['measure_key' => $measure]);
        }

        Schema::table('rf_indicators', function (Blueprint $table): void {
            $table->dropColumn('source');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('rf_indicators', 'measure_key')) {
            return;
        }

        if (DB::table('rf_indicators')->whereNotIn('measure_key', array_values(self::SOURCES))
            ->orWhereNotNull('measure_filters')->exists()) {
            throw new RuntimeException('New measures or filters cannot be represented by legacy indicator sources.');
        }

        Schema::table('rf_indicators', function (Blueprint $table): void {
            $table->string('source', 32)->default('manual');
        });

        foreach (self::SOURCES as $source => $measure) {
            DB::table('rf_indicators')->where('measure_key', $measure)->update(['source' => $source]);
        }

        Schema::table('rf_indicators', function (Blueprint $table): void {
            $table->dropColumn(['measure_key', 'measure_filters']);
        });
    }
};
