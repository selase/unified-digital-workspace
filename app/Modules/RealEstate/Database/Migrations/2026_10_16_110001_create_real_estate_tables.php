<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Master Real Estate Projects Table
        Schema::create('real_estate_projects', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->string('name');
            $table->string('code');
            $table->string('location');
            $table->string('region')->default('Greater Accra');
            $table->text('description')->nullable();
            $table->decimal('total_acreage', 10, 2)->default(0.00);
            $table->string('status')->default('active'); // active, planning, sold_out
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
        });

        // 2. Land Parcels / Plots with 4-Stage Compliance & Bounding Geometry
        Schema::create('land_parcels', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('real_estate_project_id')->constrained('real_estate_projects')->cascadeOnDelete();
            $table->string('plot_number');
            $table->string('block_number')->nullable();
            $table->string('size_dimension')->default('70x100 ft');
            $table->decimal('acreage', 8, 4)->default(0.1600);
            $table->unsignedBigInteger('price_pesewas')->default(0);
            $table->enum('tenure_type', ['STOOL', 'FAMILY', 'PRIVATE', 'STATE'])->default('PRIVATE');
            $table->string('current_stage')->default('IN_DUE_DILIGENCE'); // IN_DUE_DILIGENCE, PURCHASE_AND_REGISTRATION, PERMITTING_AND_PRE_CONSTRUCTION, PROJECT_READY_FOR_BUILD
            $table->boolean('is_legacy')->default(false);
            $table->string('status')->default('available'); // available, reserved, allocated, sold

            // Spatial Coordinates & Bounding Box for Overlap Detection
            $table->decimal('min_lat', 10, 7)->nullable();
            $table->decimal('max_lat', 10, 7)->nullable();
            $table->decimal('min_lng', 10, 7)->nullable();
            $table->decimal('max_lng', 10, 7)->nullable();
            $table->json('boundary_coordinates_json')->nullable();

            // Phase 1: Due Diligence
            $table->date('inspection_date')->nullable();
            $table->string('lands_commission_search_number')->nullable();
            $table->date('search_issue_date')->nullable();
            $table->boolean('has_traditional_consent_letter')->default(false);
            $table->boolean('has_state_allocation_note')->default(false);
            $table->boolean('is_due_diligence_verified')->default(false);
            $table->uuid('due_diligence_verified_by_id')->nullable();
            $table->dateTime('due_diligence_verified_at')->nullable();

            // Phase 2: Purchase & Registration
            $table->string('indenture_reference')->nullable();
            $table->string('land_title_certificate_number')->nullable();
            $table->boolean('stamp_duty_paid')->default(false);
            $table->boolean('pillars_and_wall_secured')->default(false);
            $table->date('registration_date')->nullable();
            $table->uuid('registration_verified_by_id')->nullable();

            // Phase 3: Assembly Permitting
            $table->string('architect_gia_number')->nullable();
            $table->string('structural_engineer_ghie_number')->nullable();
            $table->string('mmda_permit_number')->nullable();
            $table->date('permit_issue_date')->nullable();
            $table->boolean('requires_epa_clearance')->default(false);
            $table->string('epa_clearance_number')->nullable();
            $table->boolean('is_permit_active')->default(false);
            $table->uuid('permit_verified_by_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'real_estate_project_id', 'plot_number']);
        });

        // 3. Document Management Vault
        Schema::create('land_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('land_parcel_id')->constrained('land_parcels')->cascadeOnDelete();
            $table->string('workflow_stage');
            $table->string('document_type'); // SEARCH_REPORT, INDENTURE, SITE_PLAN, CONSENT_LETTER, BUILDING_PERMIT, TITLE_CERTIFICATE, EPA_CLEARANCE, ALLOCATION_LETTER, PAYMENT_RECEIPT, LEGACY_DEED
            $table->string('title');
            $table->string('file_path');
            $table->string('file_hash')->nullable();
            $table->boolean('is_legacy')->default(false);
            $table->string('verification_status')->default('PENDING'); // PENDING, VERIFIED, REJECTED
            $table->uuid('uploaded_by_id')->nullable();
            $table->uuid('verified_by_id')->nullable();
            $table->timestamps();
        });

        // 4. Client Plot Allocations (Purchases / Installment Contracts)
        Schema::create('client_plot_allocations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('land_parcel_id')->constrained('land_parcels')->cascadeOnDelete();
            $table->uuid('client_user_id')->index(); // references users.uuid
            $table->string('allocation_number');
            $table->unsignedBigInteger('total_price_pesewas')->default(0);
            $table->unsignedBigInteger('amount_paid_pesewas')->default(0);
            $table->string('status')->default('active'); // active, completed, defaulted, cancelled
            $table->dateTime('allocated_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'allocation_number']);
        });

        // 5. Installment Schedules
        Schema::create('installment_schedules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('client_plot_allocation_id')->constrained('client_plot_allocations')->cascadeOnDelete();
            $table->integer('installment_number');
            $table->date('due_date');
            $table->unsignedBigInteger('amount_due_pesewas');
            $table->unsignedBigInteger('amount_paid_pesewas')->default(0);
            $table->string('status')->default('pending'); // pending, paid, overdue, partially_paid
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamps();
        });

        // 6. Property Public Listings
        Schema::create('property_listings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('land_parcel_id')->nullable()->constrained('land_parcels')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('location');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_pesewas')->default(0);
            $table->string('size_dimension')->default('70x100 ft');
            $table->string('tenure_type')->default('PRIVATE');
            $table->string('compliance_stage')->default('IN_DUE_DILIGENCE');
            $table->json('features_json')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_listings');
        Schema::dropIfExists('installment_schedules');
        Schema::dropIfExists('client_plot_allocations');
        Schema::dropIfExists('land_documents');
        Schema::dropIfExists('land_parcels');
        Schema::dropIfExists('real_estate_projects');
    }
};
