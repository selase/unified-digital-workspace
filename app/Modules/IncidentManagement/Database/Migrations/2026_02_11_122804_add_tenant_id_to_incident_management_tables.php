<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('incident_assignments', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('incident_tasks', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('incident_comments', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('incident_attachments', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('incident_escalations', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('incident_reminders', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('incident_progress_reports', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('incident_progress_comments', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('incident_slas', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        DB::statement('UPDATE incident_assignments SET tenant_id = (SELECT tenant_id FROM incidents WHERE incidents.id = incident_assignments.incident_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE incident_tasks SET tenant_id = (SELECT tenant_id FROM incidents WHERE incidents.id = incident_tasks.incident_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE incident_comments SET tenant_id = (SELECT tenant_id FROM incidents WHERE incidents.id = incident_comments.incident_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE incident_attachments SET tenant_id = (SELECT tenant_id FROM incidents WHERE incidents.id = incident_attachments.incident_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE incident_escalations SET tenant_id = (SELECT tenant_id FROM incidents WHERE incidents.id = incident_escalations.incident_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE incident_reminders SET tenant_id = (SELECT tenant_id FROM incidents WHERE incidents.id = incident_reminders.incident_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE incident_progress_reports SET tenant_id = (SELECT tenant_id FROM incidents WHERE incidents.id = incident_progress_reports.incident_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE incident_progress_comments SET tenant_id = (SELECT tenant_id FROM incident_progress_reports WHERE incident_progress_reports.id = incident_progress_comments.progress_report_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE incident_slas SET tenant_id = (SELECT tenant_id FROM incidents WHERE incidents.id = incident_slas.incident_id) WHERE tenant_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incident_assignments', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('incident_tasks', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('incident_comments', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('incident_attachments', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('incident_escalations', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('incident_reminders', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('incident_progress_reports', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('incident_progress_comments', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('incident_slas', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });
    }
};
