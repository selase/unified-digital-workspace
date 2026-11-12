<?php

declare(strict_types=1);

use App\Modules\ResultsFramework\Support\RollbackPreflight;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rf_indicators', 'source')) {
            return;
        }

        if (DB::table('rf_indicators')->whereNotIn('source', ['manual', ...array_keys(RollbackPreflight::LEGACY_MEASURES)])->exists()) {
            throw new RuntimeException('An indicator uses an unrecognised legacy source.');
        }

        Schema::table('rf_indicators', function (Blueprint $table): void {
            $table->string('measure_key')->nullable();
            $table->json('measure_filters')->nullable();
        });

        foreach (RollbackPreflight::LEGACY_MEASURES as $source => $measure) {
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

        if ((new RollbackPreflight)->hasUnrepresentableMeasures(DB::connection())) {
            throw new RuntimeException('New measures or filters cannot be represented by legacy indicator sources.');
        }

        Schema::table('rf_indicators', function (Blueprint $table): void {
            $table->string('source', 32)->default('manual');
        });

        foreach (RollbackPreflight::LEGACY_MEASURES as $source => $measure) {
            DB::table('rf_indicators')->where('measure_key', $measure)->update(['source' => $source]);
        }

        Schema::table('rf_indicators', function (Blueprint $table): void {
            $table->dropColumn(['measure_key', 'measure_filters']);
        });
    }
};
