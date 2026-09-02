<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_daily_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('checklist_date');
            $table->string('title', 255);
            $table->boolean('is_done')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'checklist_date', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_daily_checklist_items');
    }
};
