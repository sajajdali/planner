<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('four_hour_report_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('color', 20);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('four_hour_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('four_hour_report_category_id')->constrained()->restrictOnDelete();
            $table->date('report_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->text('description');
            $table->timestamps();

            $table->index(['user_id', 'report_date', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('four_hour_reports');
        Schema::dropIfExists('four_hour_report_categories');
    }
};
