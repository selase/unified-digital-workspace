<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qm_alerts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('escalation_level')->default(0);
            $table->timestampTz('escalated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('qm_alerts', function (Blueprint $table): void {
            $table->dropColumn(['escalation_level', 'escalated_at']);
        });
    }
};
