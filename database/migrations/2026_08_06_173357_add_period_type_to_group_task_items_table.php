<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('group_task_items', 'period_type')) {
            Schema::table('group_task_items', function (Blueprint $table) {
                $table->string('period_type', 20)->default('general')->after('title');
            });
        }

        if (Schema::hasColumn('group_task_projects', 'period_type')) {
            DB::table('group_task_items')
                ->select('group_task_items.id', 'group_task_projects.period_type')
                ->join('group_task_projects', 'group_task_projects.id', '=', 'group_task_items.group_task_project_id')
                ->orderBy('group_task_items.id')
                ->each(function ($item) {
                    DB::table('group_task_items')
                        ->where('id', $item->id)
                        ->update(['period_type' => $item->period_type ?? 'general']);
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('group_task_items', 'period_type')) {
            Schema::table('group_task_items', function (Blueprint $table) {
                $table->dropColumn('period_type');
            });
        }
    }
};
