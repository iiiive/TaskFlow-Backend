<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_issue_type_check');
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_issue_type_check
            CHECK (issue_type IN ('epic', 'story', 'task', 'subtask', 'bug', 'improvement', 'change_request', 'incident', 'service_request', 'feature_request', 'other'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_issue_type_check');
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_issue_type_check
            CHECK (issue_type IN ('epic', 'story', 'task', 'subtask', 'bug', 'improvement', 'change_request', 'incident', 'service_request', 'other'))");
    }
};
