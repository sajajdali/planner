<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone')->default('Asia/Tehran')->after('password');
            }

            if (! Schema::hasColumn('users', 'locale')) {
                $table->string('locale')->default('fa')->after('timezone');
            }

            if (! Schema::hasColumn('users', 'settings')) {
                $table->json('settings')->nullable()->after('locale');
            }
        });
    }
};
