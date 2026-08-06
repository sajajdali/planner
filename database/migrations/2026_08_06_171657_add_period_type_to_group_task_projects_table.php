<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_task_items')) {
            DB::table('group_task_items')->delete();
        }

        DB::table('group_task_projects')->delete();

        if (! Schema::hasColumn('group_task_projects', 'period_type')) {
            Schema::table('group_task_projects', function (Blueprint $table) {
                $table->string('period_type', 20)->default('general')->after('task_group_id');
            });
        }

        $this->dropForeignKeys(['user_id', 'category_id', 'task_group_id']);
        $this->ensureSupportingIndexes();

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

        $this->restoreForeignKeys();
    }

    public function down(): void
    {
        $this->dropForeignKeys(['user_id', 'category_id', 'task_group_id']);

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

        if (Schema::hasColumn('group_task_projects', 'period_type')) {
            Schema::table('group_task_projects', function (Blueprint $table) {
                $table->dropColumn('period_type');
            });
        }

        $this->restoreForeignKeys();
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

    private function dropForeignKeys(array $columns): void
    {
        foreach ($columns as $column) {
            $foreignKey = $this->foreignKeyName($column);

            if (! $foreignKey) {
                continue;
            }

            Schema::table('group_task_projects', function (Blueprint $table) use ($foreignKey) {
                $table->dropForeign($foreignKey);
            });
        }
    }

    private function ensureSupportingIndexes(): void
    {
        $indexes = [
            'user_id' => 'group_task_projects_user_id_index',
            'category_id' => 'group_task_projects_category_id_index',
            'task_group_id' => 'group_task_projects_task_group_id_index',
        ];

        foreach ($indexes as $column => $indexName) {
            if ($this->hasIndex($indexName)) {
                continue;
            }

            Schema::table('group_task_projects', function (Blueprint $table) use ($column, $indexName) {
                $table->index($column, $indexName);
            });
        }
    }

    private function restoreForeignKeys(): void
    {
        $foreignKeys = [
            'user_id' => ['users', 'id', 'group_task_projects_user_id_foreign'],
            'category_id' => ['categories', 'id', 'group_task_projects_category_id_foreign'],
            'task_group_id' => ['task_groups', 'id', 'group_task_projects_task_group_id_foreign'],
        ];

        foreach ($foreignKeys as $column => [$tableName, $referenceColumn, $foreignKeyName]) {
            if ($this->foreignKeyName($column)) {
                continue;
            }

            Schema::table('group_task_projects', function (Blueprint $table) use ($column, $tableName, $referenceColumn, $foreignKeyName) {
                $table->foreign($column, $foreignKeyName)
                    ->references($referenceColumn)
                    ->on($tableName)
                    ->cascadeOnDelete();
            });
        }
    }
};
