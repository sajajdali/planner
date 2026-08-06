<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_task_projects', function (Blueprint $table) {
            $table->string('period_type', 20)->default('general')->after('task_group_id');
            $table->dropUnique(['user_id', 'task_group_id']);
            $table->unique(['user_id', 'task_group_id', 'period_type']);
        });
    }

    public function down(): void
    {
        Schema::table('group_task_projects', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'task_group_id', 'period_type']);
            $table->unique(['user_id', 'task_group_id']);
            $table->dropColumn('period_type');
        });
    }
};
