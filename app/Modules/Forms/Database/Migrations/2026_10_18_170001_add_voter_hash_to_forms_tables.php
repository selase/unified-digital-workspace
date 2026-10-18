<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('poll_votes') && ! Schema::hasColumn('poll_votes', 'voter_hash')) {
            Schema::table('poll_votes', function (Blueprint $table): void {
                $table->string('voter_hash', 64)->nullable()->after('ip_address')->index();
            });
        }

        if (Schema::hasTable('form_submissions') && ! Schema::hasColumn('form_submissions', 'voter_hash')) {
            Schema::table('form_submissions', function (Blueprint $table): void {
                $table->string('voter_hash', 64)->nullable()->after('ip_address')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('poll_votes') && Schema::hasColumn('poll_votes', 'voter_hash')) {
            Schema::table('poll_votes', function (Blueprint $table): void {
                $table->dropIndex(['voter_hash']);
                $table->dropColumn('voter_hash');
            });
        }

        if (Schema::hasTable('form_submissions') && Schema::hasColumn('form_submissions', 'voter_hash')) {
            Schema::table('form_submissions', function (Blueprint $table): void {
                $table->dropIndex(['voter_hash']);
                $table->dropColumn('voter_hash');
            });
        }
    }
};
