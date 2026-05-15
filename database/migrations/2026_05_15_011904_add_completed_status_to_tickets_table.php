<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE tickets
            MODIFY status ENUM(
                'todo',
                'ready_for_development',
                'dev_in_progress',
                'ready_for_testing',
                'ready_for_uat',
                'done',
                'completed'
            ) NOT NULL DEFAULT 'todo'
        ");
    }

    public function down(): void
    {
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
};