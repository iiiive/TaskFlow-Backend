<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE team_members DROP CONSTRAINT IF EXISTS team_members_role_check');
        DB::statement("ALTER TABLE team_members ADD CONSTRAINT team_members_role_check CHECK (role IN ('project_manager','team_lead','developer','tester','viewer','client'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE team_members DROP CONSTRAINT IF EXISTS team_members_role_check');
        DB::statement("ALTER TABLE team_members ADD CONSTRAINT team_members_role_check CHECK (role IN ('team_lead','member'))");
    }
};
