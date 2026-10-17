<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_message_recipients', function (Blueprint $table): void {
            if (! Schema::hasColumn('forum_message_recipients', 'role')) {
                $table->string('role', 16)->default('to')->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('forum_message_recipients', function (Blueprint $table): void {
            if (Schema::hasColumn('forum_message_recipients', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
