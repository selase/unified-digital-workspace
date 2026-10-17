<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('posts') && ! Schema::hasColumn('posts', 'audience')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->string('audience')->default('public')->index(); // public, internal_only, departmental
                $table->unsignedBigInteger('department_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('media') && ! Schema::hasColumn('media', 'is_internal')) {
            Schema::table('media', function (Blueprint $table): void {
                $table->boolean('is_internal')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('posts') && Schema::hasColumn('posts', 'audience')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->dropColumn(['audience', 'department_id']);
            });
        }

        if (Schema::hasTable('media') && Schema::hasColumn('media', 'is_internal')) {
            Schema::table('media', function (Blueprint $table): void {
                $table->dropColumn('is_internal');
            });
        }
    }
};
