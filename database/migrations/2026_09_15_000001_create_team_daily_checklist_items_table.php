<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_daily_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->date('checklist_date')->index();
            $table->boolean('is_done')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'checklist_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_daily_checklist_items');
    }
};
