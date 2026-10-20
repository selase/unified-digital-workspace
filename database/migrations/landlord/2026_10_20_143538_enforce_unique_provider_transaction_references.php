<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->table('transactions', function (Blueprint $table): void {
            $table->unique(['provider', 'provider_transaction_id'], 'transactions_provider_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('transactions', function (Blueprint $table): void {
            $table->dropUnique('transactions_provider_reference_unique');
        });
    }
};
