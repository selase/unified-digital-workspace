<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection('landlord')->create('hybrid_capacity_counters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('meter', 32);
            $table->string('period', 10);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->timestamp('audited_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'meter', 'period']);
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('hybrid_capacity_counters');
    }
};
