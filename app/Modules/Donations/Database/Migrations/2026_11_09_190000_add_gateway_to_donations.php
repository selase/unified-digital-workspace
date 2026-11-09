<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('don_donations') && ! Schema::hasColumn('don_donations', 'gateway_reference')) {
            Schema::table('don_donations', function (Blueprint $table): void {
                $table->string('gateway', 24)->nullable()->after('reference');
                $table->string('gateway_reference')->nullable()->after('gateway');
                $table->unique(['tenant_id', 'gateway', 'gateway_reference']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('don_donations', 'gateway_reference')) {
            Schema::table('don_donations', function (Blueprint $table): void {
                $table->dropUnique(['tenant_id', 'gateway', 'gateway_reference']);
                $table->dropColumn(['gateway', 'gateway_reference']);
            });
        }
    }
};
