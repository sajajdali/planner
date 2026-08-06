<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('group_task_projects', 'period_type')) {
            Schema::table('group_task_projects', function (Blueprint $table) {
                $table->string('period_type', 20)->default('general')->after('task_group_id');
            });
        }

        if (! $this->hasIndex('group_task_projects_task_group_id_index')) {
            Schema::table('group_task_projects', function (Blueprint $table) {
                $table->index('task_group_id', 'group_task_projects_task_group_id_index');
            });
        }

        if ($this->hasIndex('group_task_projects_user_id_task_group_id_unique')) {
            Schema::table('group_task_projects', function (Blueprint $table) {
                $table->dropUnique('group_task_projects_user_id_task_group_id_unique');
            });
        }

        if (! $this->hasIndex('group_task_projects_user_id_task_group_id_period_type_unique')) {
            Schema::table('group_task_projects', function (Blueprint $table) {
                $table->unique(['user_id', 'task_group_id', 'period_type'], 'group_task_projects_user_id_task_group_id_period_type_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('group_task_projects_user_id_task_group_id_period_type_unique')) {
            Schema::table('group_task_projects', function (Blueprint $table) {
                $table->dropUnique('group_task_projects_user_id_task_group_id_period_type_unique');
            });
        }

        if (! $this->hasIndex('group_task_projects_user_id_task_group_id_unique')) {
            Schema::table('group_task_projects', function (Blueprint $table) {
                $table->unique(['user_id', 'task_group_id'], 'group_task_projects_user_id_task_group_id_unique');
            });
        }

        if ($this->hasIndex('group_task_projects_task_group_id_index')) {
            Schema::table('group_task_projects', function (Blueprint $table) {
                $table->dropIndex('group_task_projects_task_group_id_index');
            });
        }

        if (Schema::hasColumn('group_task_projects', 'period_type')) {
            Schema::table('group_task_projects', function (Blueprint $table) {
                $table->dropColumn('period_type');
            });
        }
    }

    private function hasIndex(string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return collect(DB::select('SHOW INDEX FROM group_task_projects WHERE Key_name = ?', [$indexName]))->isNotEmpty();
        }

        if ($driver === 'sqlite') {
            return collect(DB::select('PRAGMA index_list(group_task_projects)'))
                ->contains(fn ($index) => ($index->name ?? null) === $indexName);
        }

        return collect(Schema::getIndexes('group_task_projects'))
            ->contains(fn ($index) => ($index['name'] ?? null) === $indexName);
    }
};
