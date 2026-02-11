<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('media_variants', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('post_revisions', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('post_meta', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        DB::statement('UPDATE media_variants SET tenant_id = (SELECT tenant_id FROM media WHERE media.id = media_variants.media_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE menu_items SET tenant_id = (SELECT tenant_id FROM menus WHERE menus.id = menu_items.menu_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE post_revisions SET tenant_id = (SELECT tenant_id FROM posts WHERE posts.id = post_revisions.post_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE post_meta SET tenant_id = (SELECT tenant_id FROM posts WHERE posts.id = post_meta.post_id) WHERE tenant_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_variants', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('post_revisions', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('post_meta', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });
    }
};
