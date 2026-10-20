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
        Schema::connection('landlord')->table('taxes', function (Blueprint $table): void {
            $table->char('country_code', 2)->nullable()->index();
        });
    }

    public function down(): void
    {
        if (DB::connection('landlord')->table('taxes')->whereNotNull('country_code')->exists()) {
            throw new RuntimeException('Remove country-specific tax rules before rollback; dropping their country would make them global.');
        }

        Schema::connection('landlord')->table('taxes', function (Blueprint $table): void {
            $table->dropIndex(['country_code']);
            $table->dropColumn('country_code');
        });
    }
};
