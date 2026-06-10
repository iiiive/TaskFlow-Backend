<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('workspace_members', 'project_members');

        DB::statement('ALTER TABLE project_members RENAME COLUMN workspace_id TO project_id');

        // Drop old role check constraint (name kept from original table creation)
        DB::statement('ALTER TABLE project_members DROP CONSTRAINT IF EXISTS workspace_members_role_check');

        // Expand to 8 roles
        DB::statement("ALTER TABLE project_members ADD CONSTRAINT project_members_role_check
            CHECK (role IN ('owner', 'admin', 'project_manager', 'team_lead', 'developer', 'tester', 'viewer', 'client'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE project_members DROP CONSTRAINT IF EXISTS project_members_role_check');

        DB::statement("ALTER TABLE project_members ADD CONSTRAINT workspace_members_role_check
            CHECK (role IN ('owner', 'editor', 'viewer'))");

        DB::statement('ALTER TABLE project_members RENAME COLUMN project_id TO workspace_id');

        Schema::rename('project_members', 'workspace_members');
    }
};
