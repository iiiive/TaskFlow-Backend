<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
    | Adds the 'completed' status. Driver-aware: MySQL redefines the ENUM,
    | Postgres swaps the CHECK constraint (see 154728 for rationale).
    */

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE tickets
                MODIFY status ENUM(
                    'todo', 'ready_for_development', 'dev_in_progress',
                    'ready_for_testing', 'ready_for_uat', 'done', 'completed'
                ) NOT NULL DEFAULT 'todo'
            ");
            return;
        }

        DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_status_check');
        DB::statement("
            ALTER TABLE tickets ADD CONSTRAINT tickets_status_check
            CHECK (status IN (
                'todo', 'ready_for_development', 'dev_in_progress',
                'ready_for_testing', 'ready_for_uat', 'done', 'completed'
            ))
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE tickets
                MODIFY status ENUM(
                    'todo', 'ready_for_development', 'dev_in_progress',
                    'ready_for_testing', 'ready_for_uat', 'done'
                ) NOT NULL DEFAULT 'todo'
            ");
            return;
        }

        DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_status_check');
        DB::statement("
            ALTER TABLE tickets ADD CONSTRAINT tickets_status_check
            CHECK (status IN (
                'todo', 'ready_for_development', 'dev_in_progress',
                'ready_for_testing', 'ready_for_uat', 'done'
            ))
        ");
    }
};
