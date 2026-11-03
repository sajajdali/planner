<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('four_hour_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('four_hour_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('four_hour_report_category_id')->constrained()->restrictOnDelete();
            $table->text('description');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('four_hour_report_items');
    }
};
