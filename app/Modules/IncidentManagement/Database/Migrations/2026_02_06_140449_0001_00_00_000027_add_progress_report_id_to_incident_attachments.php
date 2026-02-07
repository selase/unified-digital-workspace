<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_attachments', function (Blueprint $table): void {
            $table->foreignId('progress_report_id')
                ->nullable()
                ->after('comment_id')
                ->constrained('incident_progress_reports')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('incident_attachments', function (Blueprint $table): void {
            $table->dropForeign(['progress_report_id']);
            $table->dropColumn('progress_report_id');
        });
    }
};
