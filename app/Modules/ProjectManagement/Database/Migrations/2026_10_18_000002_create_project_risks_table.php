<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_risks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id')->index();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('operational');
            $table->unsignedTinyInteger('likelihood')->default(3);
            $table->unsignedTinyInteger('impact')->default(3);
            $table->integer('risk_score')->storedAs('likelihood * impact');
            $table->string('status')->default('identified');
            $table->text('mitigation_strategy')->nullable();
            $table->text('contingency_plan')->nullable();
            $table->uuid('owner_id')->nullable()->index();
            $table->timestamps();

            $table->index(['tenant_id', 'project_id', 'status']);
        });

        if (DB::connection($this->getConnection())->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE project_risks ADD CONSTRAINT check_project_risks_likelihood CHECK (likelihood >= 1 AND likelihood <= 5)');
            DB::statement('ALTER TABLE project_risks ADD CONSTRAINT check_project_risks_impact CHECK (impact >= 1 AND impact <= 5)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_risks');
    }
};
