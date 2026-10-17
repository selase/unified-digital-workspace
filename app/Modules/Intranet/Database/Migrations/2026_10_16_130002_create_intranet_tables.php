<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Intranet Upcoming Events
        Schema::create('intranet_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->date('event_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location')->default('Main Auditorium');
            $table->uuid('organizer_id')->nullable(); // references users.uuid
            $table->string('category')->default('all_hands'); // all_hands, training, board_meeting, social, departmental
            $table->boolean('is_virtual')->default(false);
            $table->string('meeting_link')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        // 2. Intranet Message Boards / Notice Boards
        Schema::create('intranet_message_boards', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        // 3. Intranet Message Board Posts
        Schema::create('intranet_board_posts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('intranet_message_board_id')->constrained('intranet_message_boards')->cascadeOnDelete();
            $table->uuid('author_id'); // references users.uuid
            $table->string('title');
            $table->text('content');
            $table->boolean('is_announcement')->default(false);
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_board_posts');
        Schema::dropIfExists('intranet_message_boards');
        Schema::dropIfExists('intranet_events');
    }
};
