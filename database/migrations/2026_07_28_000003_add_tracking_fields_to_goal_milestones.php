<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goal_milestones', function (Blueprint $table) {
            $table->decimal('weight', 8, 2)->default(1)->after('description');
            $table->date('starts_on')->nullable()->after('weight');
            $table->date('ends_on')->nullable()->after('starts_on');
            $table->string('status', 40)->default('pending')->after('ends_on');
            $table->unsignedTinyInteger('progress')->default(0)->after('status');
            $table->string('dependency')->nullable()->after('progress');
        });
    }

    public function down(): void
    {
        Schema::table('goal_milestones', function (Blueprint $table) {
            $table->dropColumn(['weight', 'starts_on', 'ends_on', 'status', 'progress', 'dependency']);
        });
    }
};
