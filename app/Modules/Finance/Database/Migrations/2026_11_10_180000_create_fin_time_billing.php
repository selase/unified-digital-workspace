<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_time_billing')) {
            Schema::create('fin_time_billing', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id')->index();
                $table->unsignedBigInteger('time_entry_id')->unique();
                $table->foreignId('invoice_id')->constrained('fin_invoices')->cascadeOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_time_billing');
    }
};
