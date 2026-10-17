<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add max_carry_forward_days to hrms_leave_categories
        Schema::table('hrms_leave_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('hrms_leave_categories', 'max_carry_forward_days')) {
                $table->integer('max_carry_forward_days')->nullable()->after('default_days');
            }
        });

        // 2. Make leave day columns in hrms_leave_balances decimal to support half days
        Schema::table('hrms_leave_balances', function (Blueprint $table): void {
            if (Schema::hasColumn('hrms_leave_balances', 'remaining_days')) {
                $table->dropColumn('remaining_days');
            }
        });

        Schema::table('hrms_leave_balances', function (Blueprint $table): void {
            $table->decimal('entitled_days', 8, 1)->default(0)->change();
            $table->decimal('carried_forward_days', 8, 1)->default(0)->change();
            $table->decimal('used_days', 8, 1)->default(0)->change();
            $table->decimal('pending_days', 8, 1)->default(0)->change();
            $table->decimal('remaining_days', 8, 1)->storedAs('entitled_days + carried_forward_days - used_days - pending_days');
        });

        // 3. Make leave day columns in hrms_leave_requests decimal
        Schema::table('hrms_leave_requests', function (Blueprint $table): void {
            $table->decimal('no_requested_days', 8, 1)->change();
            $table->decimal('no_recommended_days', 8, 1)->nullable()->change();
            $table->decimal('no_of_days_approved', 8, 1)->nullable()->change();
            $table->decimal('no_of_days_recalled', 8, 1)->nullable()->change();
            $table->decimal('balance_at_request', 8, 1)->nullable()->change();
            $table->decimal('carry_forward_at_request', 8, 1)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('hrms_leave_requests', function (Blueprint $table): void {
            $table->integer('no_requested_days')->change();
            $table->integer('no_recommended_days')->nullable()->change();
            $table->integer('no_of_days_approved')->nullable()->change();
            $table->integer('no_of_days_recalled')->nullable()->change();
            $table->integer('balance_at_request')->nullable()->change();
            $table->integer('carry_forward_at_request')->nullable()->change();
        });

        Schema::table('hrms_leave_balances', function (Blueprint $table): void {
            if (Schema::hasColumn('hrms_leave_balances', 'remaining_days')) {
                $table->dropColumn('remaining_days');
            }
        });

        Schema::table('hrms_leave_balances', function (Blueprint $table): void {
            $table->integer('entitled_days')->default(0)->change();
            $table->integer('carried_forward_days')->default(0)->change();
            $table->integer('used_days')->default(0)->change();
            $table->integer('pending_days')->default(0)->change();
            $table->integer('remaining_days')->storedAs('entitled_days + carried_forward_days - used_days - pending_days');
        });

        Schema::table('hrms_leave_categories', function (Blueprint $table): void {
            if (Schema::hasColumn('hrms_leave_categories', 'max_carry_forward_days')) {
                $table->dropColumn('max_carry_forward_days');
            }
        });
    }
};
