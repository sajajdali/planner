<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notebook_note_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('#9B5DE5');
            $table->string('icon', 24)->default('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'sort_order']);
        });

        Schema::create('notebook_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notebook_note_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('content_type', 16)->default('text');
            $table->string('language', 40)->nullable();
            $table->boolean('is_important')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'notebook_note_group_id', 'sort_order'], 'notebook_notes_user_group_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notebook_notes');
        Schema::dropIfExists('notebook_note_groups');
    }
};
