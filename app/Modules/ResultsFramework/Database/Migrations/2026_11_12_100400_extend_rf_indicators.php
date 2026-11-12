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
        Schema::table('rf_indicators', function (Blueprint $table): void {
            $table->text('definition')->nullable();
            $table->text('means_of_verification')->nullable();
            $table->dropColumn('programme');
        });
    }

    public function down(): void
    {
        Schema::table('rf_indicators', function (Blueprint $table): void {
            $table->string('programme')->nullable()->index();
            $table->dropColumn(['definition', 'means_of_verification']);
        });

        DB::table('rf_programmes')->select('id', 'tenant_id', 'name')->chunkById(500, function ($programmes): void {
            foreach ($programmes as $programme) {
                DB::table('rf_indicators')->where('tenant_id', $programme->tenant_id)
                    ->where('programme_id', $programme->id)->update(['programme' => $programme->name]);
            }
        });
    }
};
