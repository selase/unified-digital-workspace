<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hrms_employees', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');

            // User association (optional - employee may not have login)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Staff identification
            $table->string('employee_staff_id')->nullable();
            $table->string('cagd_staff_id')->nullable(); // Controller and Accountant General's Dept ID
            $table->string('file_number')->nullable();

            // Personal information
            $table->string('title', 20)->nullable(); // Mr., Mrs., Ms., Dr., Prof., etc.
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('maiden_name')->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('marital_status', 30)->nullable();

            // Contact information
            $table->string('email')->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('home_phone', 30)->nullable();
            $table->string('postal_address')->nullable();
            $table->string('residential_address')->nullable();
            $table->string('town')->nullable();
            $table->string('region')->nullable();
            $table->string('gps_postcode', 50)->nullable();

            // Disability information
            $table->boolean('is_any_disability')->default(false);
            $table->text('disability_details')->nullable();

            // Spouse information
            $table->string('name_of_spouse')->nullable();
            $table->string('spouse_phone_number', 30)->nullable();

            // Children information
            $table->boolean('is_any_children')->default(false);
            $table->integer('number_of_children')->nullable();

            // Employment details
            $table->string('social_security_number', 50)->nullable();
            $table->foreignId('grade_id')->nullable()->constrained('hrms_grades')->nullOnDelete();
            $table->foreignId('center_id')->nullable()->constrained('hrms_centers')->nullOnDelete();
            $table->foreignId('job_title_id')->nullable();

            // Profile
            $table->string('profile_photo_path')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('tenant_id');
            $table->index(['tenant_id', 'employee_staff_id']);
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hrms_employees');
    }
};
