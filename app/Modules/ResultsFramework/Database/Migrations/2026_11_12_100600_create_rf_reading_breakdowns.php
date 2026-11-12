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
        if (Schema::hasTable('rf_reading_breakdowns')) {
            return;
        }

        Schema::create('rf_reading_breakdowns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('reading_id')->constrained('rf_readings')->cascadeOnDelete();
            $table->string('dimension', 24);
            $table->string('bucket');
            $table->decimal('value', 14, 2);
            $table->unique(['reading_id', 'dimension', 'bucket']);
        });
    }

    public function down(): void
    {
        (new RollbackPreflight)->assertTenantCanRollback(DB::connection());

        Schema::dropIfExists('rf_reading_breakdowns');
    }
};
