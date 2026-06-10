<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('issue_type', 30)->default('task')->after('project_id');
            $table->foreignId('parent_ticket_id')
                ->nullable()
                ->after('issue_type')
                ->constrained('tickets')
                ->nullOnDelete();
            $table->string('issue_number', 20)->nullable()->after('parent_ticket_id');
            $table->foreignId('reporter_id')
                ->nullable()
                ->after('assigned_to')
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedSmallInteger('story_points')->nullable()->after('priority');
            $table->string('category', 100)->nullable()->after('story_points');
        });

        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_issue_type_check
            CHECK (issue_type IN ('epic', 'story', 'task', 'subtask', 'bug', 'improvement', 'change_request', 'incident', 'service_request', 'other'))");

        // Unique issue number per project
        DB::statement('CREATE UNIQUE INDEX tickets_project_issue_number_unique ON tickets (project_id, issue_number) WHERE issue_number IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tickets_project_issue_number_unique');
        DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_issue_type_check');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_ticket_id');
            $table->dropConstrainedForeignId('reporter_id');
            $table->dropColumn(['issue_type', 'issue_number', 'story_points', 'category']);
        });
    }
};
