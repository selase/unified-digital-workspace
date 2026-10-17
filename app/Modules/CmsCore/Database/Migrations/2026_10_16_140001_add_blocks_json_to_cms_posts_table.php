<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('posts') && ! Schema::hasColumn('posts', 'blocks_json')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->json('blocks_json')->nullable()->after('body');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('posts') && Schema::hasColumn('posts', 'blocks_json')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->dropColumn('blocks_json');
            });
        }
    }
};
