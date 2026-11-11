<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ben_services') && ! Schema::hasColumn('ben_services', 'client_ref')) {
            Schema::table('ben_services', function (Blueprint $table): void {
                $table->string('client_ref', 64)->nullable();
                $table->unique(['tenant_id', 'client_ref']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ben_services', 'client_ref')) {
            Schema::table('ben_services', function (Blueprint $table): void {
                $table->dropUnique(['tenant_id', 'client_ref']);
                $table->dropColumn('client_ref');
            });
        }
    }
};
