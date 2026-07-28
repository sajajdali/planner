<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 40)->default('numeric');
            $table->string('category', 80)->default('رشد فردی');
            $table->string('color', 20)->default('#2563EB');
            $table->string('icon', 40)->default('target');
            $table->string('status', 40)->default('planned');
            $table->decimal('start_value', 12, 2)->default(0);
            $table->decimal('current_value', 12, 2)->default(0);
            $table->decimal('target_value', 12, 2)->default(100);
            $table->string('unit', 40)->default('٪');
            $table->string('direction', 20)->default('increase');
            $table->date('deadline')->nullable();
            $table->text('why')->nullable();
            $table->string('next_action')->nullable();
            $table->string('last_activity_label')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('goal_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_done')->default(false);
            $table->string('date_label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('goal_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('when_label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('goal_progress_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->decimal('value', 12, 2);
            $table->unsignedTinyInteger('energy')->default(3);
            $table->text('note')->nullable();
            $table->timestamp('logged_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_progress_logs');
        Schema::dropIfExists('goal_plan_items');
        Schema::dropIfExists('goal_milestones');
        Schema::dropIfExists('goals');
    }
};
