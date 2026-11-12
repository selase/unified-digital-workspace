<?php

declare(strict_types=1);

use App\Modules\ResultsFramework\Support\RollbackPreflight;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rf_results', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('programme_id')->constrained('rf_programmes')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('rf_results')->restrictOnDelete();
            $table->string('level', 16);
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('assumptions')->nullable();
            $table->text('risks')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'programme_id', 'sort_order']);
        });

        Schema::table('rf_indicators', function (Blueprint $table): void {
            $table->foreignId('result_id')->nullable()->constrained('rf_results')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        (new RollbackPreflight)->assertTenantCanRollback(DB::connection());

        Schema::table('rf_indicators', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('result_id');
        });
        Schema::dropIfExists('rf_results');
    }
};
