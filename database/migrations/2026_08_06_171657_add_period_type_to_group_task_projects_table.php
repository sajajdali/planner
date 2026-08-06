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

        $taskGroupForeignKey = $this->foreignKeyName('task_group_id');

        if ($taskGroupForeignKey) {
            Schema::table('group_task_projects', function (Blueprint $table) use ($taskGroupForeignKey) {
                $table->dropForeign($taskGroupForeignKey);
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

        if (! $this->foreignKeyName('task_group_id')) {
            Schema::table('group_task_projects', function (Blueprint $table) {
                $table->foreign('task_group_id', 'group_task_projects_task_group_id_foreign')
                    ->references('id')
                    ->on('task_groups')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $taskGroupForeignKey = $this->foreignKeyName('task_group_id');

        if ($taskGroupForeignKey) {
            Schema::table('group_task_projects', function (Blueprint $table) use ($taskGroupForeignKey) {
                $table->dropForeign($taskGroupForeignKey);
            });
        }

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

        if (! $this->foreignKeyName('task_group_id')) {
            Schema::table('group_task_projects', function (Blueprint $table) {
                $table->foreign('task_group_id', 'group_task_projects_task_group_id_foreign')
                    ->references('id')
                    ->on('task_groups')
                    ->cascadeOnDelete();
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

    private function foreignKeyName(string $columnName): ?string
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $database = DB::getDatabaseName();
            $result = DB::selectOne(
                'select CONSTRAINT_NAME as name
                 from information_schema.KEY_COLUMN_USAGE
                 where TABLE_SCHEMA = ?
                   and TABLE_NAME = ?
                   and COLUMN_NAME = ?
                   and REFERENCED_TABLE_NAME is not null
                 limit 1',
                [$database, 'group_task_projects', $columnName]
            );

            return $result->name ?? null;
        }

        if ($driver === 'sqlite') {
            return null;
        }

        return null;
    }
};
