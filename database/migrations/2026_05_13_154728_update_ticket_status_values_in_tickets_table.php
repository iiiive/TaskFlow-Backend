<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Migrate ticket status values to the new workflow.
    |--------------------------------------------------------------------------
    | Originally written for MySQL (ALTER TABLE ... MODIFY ... ENUM). That
    | syntax is invalid on PostgreSQL, so this is now driver-aware:
    |   - MySQL  : keeps the original ENUM redefinition.
    |   - Postgres: manages the allowed values via a CHECK constraint
    |     (the same pattern used by the issue_type / role migrations).
    | The data-conversion UPDATEs run on both and are no-ops on a fresh DB.
    */

    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // Step 1: temporarily allow BOTH old and new values so existing
            // rows are not truncated.
            DB::statement("
                ALTER TABLE tickets
                MODIFY status ENUM(
                    'backlog', 'todo', 'in_progress', 'in_review',
                    'ready_for_development', 'dev_in_progress',
                    'ready_for_testing', 'ready_for_uat', 'done'
                ) NOT NULL DEFAULT 'todo'
            ");
        } else {
            // Postgres: drop the existing CHECK so the conversion below is
            // unconstrained while values are being rewritten.
            DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_status_check');
        }

        // Step 2: convert old statuses to the new workflow (no-op on empty DB).
        DB::table('tickets')->where('status', 'backlog')->update(['status' => 'todo']);
        DB::table('tickets')->where('status', 'in_progress')->update(['status' => 'dev_in_progress']);
        DB::table('tickets')->where('status', 'in_review')->update(['status' => 'ready_for_testing']);

        // Step 3: lock the column down to the final value set.
        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE tickets
                MODIFY status ENUM(
                    'todo', 'ready_for_development', 'dev_in_progress',
                    'ready_for_testing', 'ready_for_uat', 'done'
                ) NOT NULL DEFAULT 'todo'
            ");
        } else {
            DB::statement("
                ALTER TABLE tickets ADD CONSTRAINT tickets_status_check
                CHECK (status IN (
                    'todo', 'ready_for_development', 'dev_in_progress',
                    'ready_for_testing', 'ready_for_uat', 'done'
                ))
            ");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE tickets
                MODIFY status ENUM(
                    'backlog', 'todo', 'in_progress', 'in_review',
                    'ready_for_development', 'dev_in_progress',
                    'ready_for_testing', 'ready_for_uat', 'done'
                ) NOT NULL DEFAULT 'todo'
            ");
        } else {
            DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_status_check');
        }

        DB::table('tickets')->where('status', 'ready_for_development')->update(['status' => 'todo']);
        DB::table('tickets')->where('status', 'dev_in_progress')->update(['status' => 'in_progress']);
        DB::table('tickets')->where('status', 'ready_for_testing')->update(['status' => 'in_review']);
        DB::table('tickets')->where('status', 'ready_for_uat')->update(['status' => 'in_review']);

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE tickets
                MODIFY status ENUM(
                    'backlog', 'todo', 'in_progress', 'in_review', 'done'
                ) NOT NULL DEFAULT 'todo'
            ");
        } else {
            DB::statement("
                ALTER TABLE tickets ADD CONSTRAINT tickets_status_check
                CHECK (status IN ('backlog', 'todo', 'in_progress', 'in_review', 'done'))
            ");
        }
    }
};
