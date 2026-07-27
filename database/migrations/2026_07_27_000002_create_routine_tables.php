<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('color', 20)->default('#22D3D0');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('daily_routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('routine_date');
            $table->time('wake_time')->nullable();
            $table->time('sleep_time')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'routine_date']);
        });

        Schema::create('daily_routine_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_routine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('routine_item_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_done')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['daily_routine_id', 'routine_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_routine_checks');
        Schema::dropIfExists('daily_routines');
        Schema::dropIfExists('routine_items');
    }
};
