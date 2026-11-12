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
        if (Schema::hasTable('rf_targets')) {
            return;
        }

        Schema::create('rf_targets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('indicator_id')->constrained('rf_indicators')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('target_value', 14, 2);
            $table->unique(['indicator_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        (new RollbackPreflight)->assertTenantCanRollback(DB::connection());

        Schema::dropIfExists('rf_targets');
    }
};
