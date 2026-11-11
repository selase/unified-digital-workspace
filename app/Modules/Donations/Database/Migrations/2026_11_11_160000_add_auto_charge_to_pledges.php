<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('don_pledges') && ! Schema::hasColumn('don_pledges', 'auto_charge')) {
            Schema::table('don_pledges', function (Blueprint $table): void {
                $table->boolean('auto_charge')->default(false);
                $table->text('authorization_code')->nullable();
                $table->string('card_last4', 4)->nullable();
                $table->string('card_brand', 24)->nullable();
                $table->timestamp('consent_at')->nullable();
                $table->unsignedTinyInteger('charge_failures')->default(0);
                $table->string('last_charge_error')->nullable();
                $table->date('last_charge_attempt_on')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('don_pledges', 'auto_charge')) {
            Schema::table('don_pledges', function (Blueprint $table): void {
                $table->dropColumn(['auto_charge', 'authorization_code', 'card_last4', 'card_brand', 'consent_at', 'charge_failures', 'last_charge_error', 'last_charge_attempt_on']);
            });
        }
    }
};
