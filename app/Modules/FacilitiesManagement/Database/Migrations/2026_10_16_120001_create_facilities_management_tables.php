<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Facility Buildings
        Schema::create('facility_buildings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->string('name');
            $table->string('code');
            $table->string('address')->nullable();
            $table->integer('floors_count')->default(1);
            $table->decimal('total_area_sqm', 10, 2)->default(0.00);
            $table->uuid('manager_id')->nullable(); // references users.uuid
            $table->string('status')->default('active'); // active, maintenance, decommissioned
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
        });

        // 2. Facility Spaces / Rooms / Units / Apartments
        Schema::create('facility_spaces', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('facility_building_id')->constrained('facility_buildings')->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->integer('floor_number')->default(0);
            $table->string('space_type')->default('office'); // office, ward, conference_room, laboratory, residential_apartment, common_area
            $table->uuid('occupant_user_id')->nullable(); // references users.uuid
            $table->boolean('is_occupied')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'facility_building_id', 'code']);
        });

        // 3. Facility Assets & Register
        Schema::create('facility_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('facility_building_id')->constrained('facility_buildings')->cascadeOnDelete();
            $table->foreignId('facility_space_id')->nullable()->constrained('facility_spaces')->nullOnDelete();
            $table->string('asset_tag');
            $table->string('name');
            $table->string('category')->default('general_maintenance'); // hvac, electrical, plumbing, fire_safety, furniture, medical_device, it_infrastructure
            $table->string('serial_number')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->unsignedBigInteger('purchase_cost_pesewas')->default(0);
            $table->date('warranty_expiry_date')->nullable();
            $table->string('status')->default('operational'); // operational, needs_repair, under_maintenance, decommissioned
            $table->string('criticality')->default('medium'); // low, medium, high, mission_critical
            $table->dateTime('last_serviced_at')->nullable();
            $table->date('next_service_due')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'asset_tag']);
        });

        // 4. Preventive Maintenance Schedules
        Schema::create('preventive_maintenance_schedules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('facility_asset_id')->constrained('facility_assets')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('frequency')->default('monthly'); // daily, weekly, monthly, quarterly, biannual, annual
            $table->integer('interval_days')->default(30);
            $table->dateTime('last_performed_at')->nullable();
            $table->date('next_due_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. Work Orders (Dual Mode: Mode A Institutional vs Mode B Occupant Marketplace)
        Schema::create('facility_work_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->string('work_order_number');
            $table->string('mode')->default('mode_a_institutional'); // mode_a_institutional, mode_b_occupant_marketplace
            $table->foreignId('facility_building_id')->nullable()->constrained('facility_buildings')->nullOnDelete();
            $table->foreignId('facility_space_id')->nullable()->constrained('facility_spaces')->nullOnDelete();
            $table->foreignId('facility_asset_id')->nullable()->constrained('facility_assets')->nullOnDelete();
            $table->uuid('requester_id'); // references users.uuid
            $table->uuid('assigned_technician_id')->nullable(); // references users.uuid
            $table->string('category')->default('general_maintenance'); // electrical, plumbing, hvac, landscaping, water_delivery, general_maintenance
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium'); // low, medium, high, emergency
            $table->string('status')->default('open'); // open, assigned, in_progress, completed, cancelled
            $table->date('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('completion_notes')->nullable();

            // Link to Vendor Marketplace ServiceJob for Mode B requests
            $table->unsignedBigInteger('service_job_id')->nullable()->index();

            $table->timestamps();

            $table->unique(['tenant_id', 'work_order_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_work_orders');
        Schema::dropIfExists('preventive_maintenance_schedules');
        Schema::dropIfExists('facility_assets');
        Schema::dropIfExists('facility_spaces');
        Schema::dropIfExists('facility_buildings');
    }
};
