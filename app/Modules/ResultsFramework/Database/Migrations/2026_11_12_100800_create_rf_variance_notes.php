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
        if (Schema::hasTable('rf_variance_notes')) {
            return;
        }

        Schema::create('rf_variance_notes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('indicator_id')->constrained('rf_indicators')->cascadeOnDelete();
            $table->text('reason');
            $table->text('corrective_action')->nullable();
            $table->string('status', 16)->default('open');
            $table->uuid('noted_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        (new RollbackPreflight)->assertTenantCanRollback(DB::connection());

        Schema::dropIfExists('rf_variance_notes');
    }
};
