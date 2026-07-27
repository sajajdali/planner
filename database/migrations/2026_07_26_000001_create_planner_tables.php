<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20);
            $table->string('soft_color', 20)->default('#EEF2FF');
            $table->string('icon')->default('circle');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('task_date')->nullable();
            $table->time('planned_start_time')->nullable();
            $table->time('planned_end_time')->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->unsignedInteger('manual_actual_minutes')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->json('recurrence_rule')->nullable();
            $table->timestamp('reminder_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_time_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->string('status')->default('running');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('follow_up_date');
            $table->time('follow_up_time')->nullable();
            $table->string('person_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('url')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('pending');
            $table->text('result_note')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('daily_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('review_date');
            $table->string('achievement')->nullable();
            $table->string('improvement_note')->nullable();
            $table->unsignedTinyInteger('satisfaction_score')->nullable();
            $table->unsignedTinyInteger('energy_score')->nullable();
            $table->unsignedTinyInteger('focus_score')->nullable();
            $table->unsignedTinyInteger('completion_percentage')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'review_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reviews');
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('task_time_sessions');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('categories');
    }
};
