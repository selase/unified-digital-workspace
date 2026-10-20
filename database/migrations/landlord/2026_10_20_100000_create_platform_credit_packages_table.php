<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('landlord');
        if ($schema->hasTable('platform_credit_packages')) {
            return;
        }
        $schema->create('platform_credit_packages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('credit_type')->index();
            $table->unsignedInteger('amount_credits');
            $table->unsignedInteger('price_cents');
            $table->string('currency', 3)->default('GHS');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * The catalogue may predate this migration and contain operator-managed offers
     * referenced by purchases, so rollback must retain its data.
     */
    public function down(): void {}
};
