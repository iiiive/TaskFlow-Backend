<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('auto_assign_enabled')->default(false)->after('last_issue_number');
            $table->string('auto_assign_strategy', 30)->default('round_robin')->after('auto_assign_enabled');
        });

        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_auto_assign_strategy_check CHECK (auto_assign_strategy IN ('round_robin', 'least_loaded'))");
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['auto_assign_enabled', 'auto_assign_strategy']);
        });
    }
};
