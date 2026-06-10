<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('workspaces', 'projects');

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('owner_id')
                ->constrained('organizations')
                ->nullOnDelete();

            $table->string('project_key', 10)->nullable()->after('description');
            $table->string('project_type', 50)->default('software')->after('project_key');
            $table->string('project_mode', 20)->default('kanban')->after('project_type');
            $table->unsignedInteger('last_issue_number')->default(0)->after('project_mode');
            $table->timestamp('archived_at')->nullable()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['project_key', 'project_type', 'project_mode', 'last_issue_number', 'archived_at']);
        });

        Schema::rename('projects', 'workspaces');
    }
};
