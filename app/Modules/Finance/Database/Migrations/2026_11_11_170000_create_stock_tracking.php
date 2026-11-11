<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_items') && ! Schema::hasColumn('fin_items', 'track_stock')) {
            Schema::table('fin_items', function (Blueprint $table): void {
                $table->boolean('track_stock')->default(false);
                $table->bigInteger('reorder_level_thousandths')->default(0);
            });
        }

        if (! Schema::hasTable('fin_stock_movements')) {
            Schema::create('fin_stock_movements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id')->index();
                $table->foreignId('item_id')->constrained('fin_items')->cascadeOnDelete();
                $table->date('moved_on');
                $table->bigInteger('quantity_thousandths');
                $table->string('kind', 16);
                $table->string('source_type', 24)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->bigInteger('unit_cost')->nullable();
                $table->string('note')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->index(['item_id', 'moved_on']);
                $table->index(['source_type', 'source_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_stock_movements');
        if (Schema::hasColumn('fin_items', 'track_stock')) {
            Schema::table('fin_items', function (Blueprint $table): void {
                $table->dropColumn(['track_stock', 'reorder_level_thousandths']);
            });
        }
    }
};
