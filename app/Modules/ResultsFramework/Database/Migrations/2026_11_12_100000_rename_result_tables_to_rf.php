<?php

declare(strict_types=1);

use App\Modules\ResultsFramework\Support\RollbackPreflight;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameIfPresent('pr_indicators', 'rf_indicators');
        $this->renameIfPresent('pr_readings', 'rf_readings');
    }

    public function down(): void
    {
        (new RollbackPreflight)->assertTenantCanRollback(DB::connection());

        $this->renameIfPresent('rf_readings', 'pr_readings');
        $this->renameIfPresent('rf_indicators', 'pr_indicators');
    }

    private function renameIfPresent(string $from, string $to): void
    {
        if (Schema::hasTable($from)) {
            Schema::rename($from, $to);
        }
    }
};
