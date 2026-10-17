<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table): void {
                if (! Schema::hasColumn('documents', 'reviewer_id')) {
                    $table->uuid('reviewer_id')->nullable()->index();
                }
                if (! Schema::hasColumn('documents', 'approver_id')) {
                    $table->uuid('approver_id')->nullable()->index();
                }
                if (! Schema::hasColumn('documents', 'review_due_at')) {
                    $table->timestampTz('review_due_at')->nullable()->index();
                }
                if (! Schema::hasColumn('documents', 'expires_at')) {
                    $table->timestampTz('expires_at')->nullable()->index();
                }
                if (! Schema::hasColumn('documents', 'review_comments')) {
                    $table->text('review_comments')->nullable();
                }
                if (! Schema::hasColumn('documents', 'supersedes_id')) {
                    $table->foreignId('supersedes_id')->nullable()->constrained('documents')->nullOnDelete();
                }
                if (! Schema::hasColumn('documents', 'superseded_by_id')) {
                    $table->foreignId('superseded_by_id')->nullable()->constrained('documents')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('document_acknowledgements')) {
            Schema::create('document_acknowledgements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
                $table->uuid('user_id')->index();
                $table->timestampTz('acknowledged_at');
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->unique(['document_id', 'user_id']);
                $table->index(['tenant_id', 'document_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_acknowledgements');

        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table): void {
                if (Schema::hasColumn('documents', 'superseded_by_id')) {
                    $table->dropForeign(['superseded_by_id']);
                    $table->dropColumn('superseded_by_id');
                }
                if (Schema::hasColumn('documents', 'supersedes_id')) {
                    $table->dropForeign(['supersedes_id']);
                    $table->dropColumn('supersedes_id');
                }
                $columns = ['reviewer_id', 'approver_id', 'review_due_at', 'expires_at', 'review_comments'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('documents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
