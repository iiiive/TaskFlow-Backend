<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Step 1: Temporarily allow BOTH old and new status values
        |--------------------------------------------------------------------------
        | This prevents MySQL from truncating existing old enum values like:
        | backlog, in_progress, in_review
        */
        DB::statement("
            ALTER TABLE tickets
            MODIFY status ENUM(
                'backlog',
                'todo',
                'in_progress',
                'in_review',
                'ready_for_development',
                'dev_in_progress',
                'ready_for_testing',
                'ready_for_uat',
                'done'
            ) NOT NULL DEFAULT 'todo'
        ");

        /*
        |--------------------------------------------------------------------------
        | Step 2: Convert old statuses to the new workflow
        |--------------------------------------------------------------------------
        */
        DB::table('tickets')
            ->where('status', 'backlog')
            ->update(['status' => 'todo']);

        DB::table('tickets')
            ->where('status', 'in_progress')
            ->update(['status' => 'dev_in_progress']);

        DB::table('tickets')
            ->where('status', 'in_review')
            ->update(['status' => 'ready_for_testing']);

        /*
        |--------------------------------------------------------------------------
        | Step 3: Final enum values only
        |--------------------------------------------------------------------------
        | Now that old statuses are converted, it is safe to remove them.
        */
        DB::statement("
            ALTER TABLE tickets
            MODIFY status ENUM(
                'todo',
                'ready_for_development',
                'dev_in_progress',
                'ready_for_testing',
                'ready_for_uat',
                'done'
            ) NOT NULL DEFAULT 'todo'
        ");
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Step 1: Temporarily allow BOTH old and new values
        |--------------------------------------------------------------------------
        */
        DB::statement("
            ALTER TABLE tickets
            MODIFY status ENUM(
                'backlog',
                'todo',
                'in_progress',
                'in_review',
                'ready_for_development',
                'dev_in_progress',
                'ready_for_testing',
                'ready_for_uat',
                'done'
            ) NOT NULL DEFAULT 'todo'
        ");

        /*
        |--------------------------------------------------------------------------
        | Step 2: Convert new statuses back to old workflow
        |--------------------------------------------------------------------------
        */
        DB::table('tickets')
            ->where('status', 'ready_for_development')
            ->update(['status' => 'todo']);

        DB::table('tickets')
            ->where('status', 'dev_in_progress')
            ->update(['status' => 'in_progress']);

        DB::table('tickets')
            ->where('status', 'ready_for_testing')
            ->update(['status' => 'in_review']);

        DB::table('tickets')
            ->where('status', 'ready_for_uat')
            ->update(['status' => 'in_review']);

        /*
        |--------------------------------------------------------------------------
        | Step 3: Restore old enum values only
        |--------------------------------------------------------------------------
        */
        DB::statement("
            ALTER TABLE tickets
            MODIFY status ENUM(
                'backlog',
                'todo',
                'in_progress',
                'in_review',
                'done'
            ) NOT NULL DEFAULT 'todo'
        ");
    }
};