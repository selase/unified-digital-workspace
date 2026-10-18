<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add audience to intranet_message_boards
        if (! Schema::hasColumn('intranet_message_boards', 'audience')) {
            Schema::table('intranet_message_boards', function (Blueprint $table): void {
                $table->string('audience')->default('internal')->index(); // public, internal, departmental
            });
        }

        // 2. Add is_pinned and parent_id to intranet_board_posts
        Schema::table('intranet_board_posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('intranet_board_posts', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->index();
            }
            if (! Schema::hasColumn('intranet_board_posts', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->constrained('intranet_board_posts')
                    ->cascadeOnDelete();
            }
        });

        // 3. Create intranet_event_rsvps table
        if (! Schema::hasTable('intranet_event_rsvps')) {
            Schema::create('intranet_event_rsvps', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('tenant_id')->index();
                $table->foreignId('intranet_event_id')->constrained('intranet_events')->cascadeOnDelete();
                $table->uuid('user_id'); // references users.uuid
                $table->string('status')->default('attending'); // attending, declined, tentative
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'intranet_event_id', 'user_id'], 'intranet_rsvps_tenant_event_user_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_event_rsvps');

        Schema::table('intranet_board_posts', function (Blueprint $table): void {
            if (Schema::hasColumn('intranet_board_posts', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }
            if (Schema::hasColumn('intranet_board_posts', 'is_pinned')) {
                $table->dropColumn('is_pinned');
            }
        });

        Schema::table('intranet_message_boards', function (Blueprint $table): void {
            if (Schema::hasColumn('intranet_message_boards', 'audience')) {
                $table->dropColumn('audience');
            }
        });
    }
};
