<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            if (! Schema::hasColumn('property_listings', 'image_path')) {
                $table->string('image_path')->nullable()->after('description');
            }
            if (! Schema::hasColumn('property_listings', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            if (Schema::hasColumn('property_listings', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('property_listings', 'image_path')) {
                $table->dropColumn('image_path');
            }
        });
    }
};
