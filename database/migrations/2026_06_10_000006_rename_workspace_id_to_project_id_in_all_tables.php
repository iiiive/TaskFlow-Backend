<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tickets RENAME COLUMN workspace_id TO project_id');
        DB::statement('ALTER TABLE kanban_columns RENAME COLUMN workspace_id TO project_id');
        DB::statement('ALTER TABLE epics RENAME COLUMN workspace_id TO project_id');
        DB::statement('ALTER TABLE activity_logs RENAME COLUMN workspace_id TO project_id');
        DB::statement('ALTER TABLE ticket_time_logs RENAME COLUMN workspace_id TO project_id');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tickets RENAME COLUMN project_id TO workspace_id');
        DB::statement('ALTER TABLE kanban_columns RENAME COLUMN project_id TO workspace_id');
        DB::statement('ALTER TABLE epics RENAME COLUMN project_id TO workspace_id');
        DB::statement('ALTER TABLE activity_logs RENAME COLUMN project_id TO workspace_id');
        DB::statement('ALTER TABLE ticket_time_logs RENAME COLUMN project_id TO workspace_id');
    }
};
