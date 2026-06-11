<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Weekly team capacity in hours (sum of member availability).
            $table->unsignedInteger('capacity_hours')->nullable()->after('color');
        });

        Schema::table('team_members', function (Blueprint $table) {
            // Per-member weekly availability in hours.
            $table->unsignedInteger('weekly_capacity_hours')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn('weekly_capacity_hours');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('capacity_hours');
        });
    }
};
