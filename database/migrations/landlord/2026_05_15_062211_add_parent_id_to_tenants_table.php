<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional parent_id self-FK so tenants can form a single-parent
 * tree (parent organization -> child branches). Permissions cascade DOWN
 * by default — an admin at the parent gains scoped access to children
 * via App\Services\Tenancy\TenantHierarchyResolver.
 *
 * Idempotent and additive: every existing row gets parent_id = NULL so
 * no tenant is unexpectedly reparented. Depth is enforced in code (we
 * cap at 3 levels by default to keep recursion bounded and the UI sane).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tenants', 'parent_id')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $table->uuid('parent_id')->nullable()->after('id');
            $table->foreign('parent_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tenants', 'parent_id')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
