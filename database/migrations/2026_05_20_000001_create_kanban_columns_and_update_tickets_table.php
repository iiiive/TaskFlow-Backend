<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Create kanban_columns table
        |--------------------------------------------------------------------------
        | This allows every workspace to have its own custom Kanban workflow.
        | Example:
        | Backlog, Ready for Development, Dev In Progress, Blockers, Done
        */
        if (!Schema::hasTable('kanban_columns')) {
            Schema::create('kanban_columns', function (Blueprint $table) {
                $table->id();

                $table->foreignId('workspace_id')
                    ->constrained('workspaces')
                    ->cascadeOnDelete();

                $table->string('name');
                $table->string('slug');
                $table->unsignedInteger('position')->default(1);

                /*
                |--------------------------------------------------------------------------
                | status_key
                |--------------------------------------------------------------------------
                | This keeps your old dashboard/archive/status logic compatible.
                | Custom columns like "Blockers" can have NULL status_key.
                */
                $table->string('status_key')->nullable();

                $table->boolean('is_backlog_column')->default(false);
                $table->boolean('is_done_column')->default(false);

                $table->timestamps();

                $table->unique(['workspace_id', 'slug']);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Make ticket.status compatible with your current workflow
        |--------------------------------------------------------------------------
        | Your original create_tickets_table migration only has:
        | todo, in_progress, in_review, done
        |
        | But your current TicketController allows:
        | ready_for_development, dev_in_progress, ready_for_testing,
        | ready_for_uat, completed
        |
        | Without this ALTER, MySQL can throw:
        | Data truncated for column 'status'
        */
        DB::statement("
            ALTER TABLE tickets
            MODIFY status ENUM(
                'todo',
                'in_progress',
                'in_review',
                'ready_for_development',
                'dev_in_progress',
                'ready_for_testing',
                'ready_for_uat',
                'done',
                'completed'
            ) NOT NULL DEFAULT 'todo'
        ");

        /*
        |--------------------------------------------------------------------------
        | 3. Add kanban_column_id to tickets
        |--------------------------------------------------------------------------
        | Tickets can now belong to a dynamic column instead of relying only on status.
        */
        if (Schema::hasTable('tickets') && !Schema::hasColumn('tickets', 'kanban_column_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->foreignId('kanban_column_id')
                    ->nullable()
                    ->after('workspace_id')
                    ->constrained('kanban_columns')
                    ->nullOnDelete();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Create default columns for existing workspaces
        |--------------------------------------------------------------------------
        | Backlog is now part of the Kanban board.
        */
        $defaultColumns = [
            [
                'name' => 'Backlog',
                'slug' => 'backlog',
                'position' => 1,
                'status_key' => 'todo',
                'is_backlog_column' => true,
                'is_done_column' => false,
            ],
            [
                'name' => 'Ready for Development',
                'slug' => 'ready-for-development',
                'position' => 2,
                'status_key' => 'ready_for_development',
                'is_backlog_column' => false,
                'is_done_column' => false,
            ],
            [
                'name' => 'Dev In Progress',
                'slug' => 'dev-in-progress',
                'position' => 3,
                'status_key' => 'dev_in_progress',
                'is_backlog_column' => false,
                'is_done_column' => false,
            ],
            [
                'name' => 'Ready for Testing',
                'slug' => 'ready-for-testing',
                'position' => 4,
                'status_key' => 'ready_for_testing',
                'is_backlog_column' => false,
                'is_done_column' => false,
            ],
            [
                'name' => 'Ready for UAT',
                'slug' => 'ready-for-uat',
                'position' => 5,
                'status_key' => 'ready_for_uat',
                'is_backlog_column' => false,
                'is_done_column' => false,
            ],
            [
                'name' => 'Done',
                'slug' => 'done',
                'position' => 6,
                'status_key' => 'done',
                'is_backlog_column' => false,
                'is_done_column' => true,
            ],
        ];

        $workspaceIds = DB::table('workspaces')->pluck('id');

        foreach ($workspaceIds as $workspaceId) {
            foreach ($defaultColumns as $column) {
                $exists = DB::table('kanban_columns')
                    ->where('workspace_id', $workspaceId)
                    ->where('slug', $column['slug'])
                    ->exists();

                if (!$exists) {
                    DB::table('kanban_columns')->insert([
                        'workspace_id' => $workspaceId,
                        'name' => $column['name'],
                        'slug' => $column['slug'],
                        'position' => $column['position'],
                        'status_key' => $column['status_key'],
                        'is_backlog_column' => $column['is_backlog_column'],
                        'is_done_column' => $column['is_done_column'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 5. Move existing tickets into matching Kanban columns
            |--------------------------------------------------------------------------
            | Old statuses are mapped safely:
            | in_progress -> dev_in_progress
            | in_review -> ready_for_testing
            */
            $tickets = DB::table('tickets')
                ->where('workspace_id', $workspaceId)
                ->whereNull('kanban_column_id')
                ->get();

            foreach ($tickets as $ticket) {
                $normalizedStatus = match ($ticket->status) {
                    'in_progress' => 'dev_in_progress',
                    'in_review' => 'ready_for_testing',
                    default => $ticket->status ?: 'todo',
                };

                $targetColumn = DB::table('kanban_columns')
                    ->where('workspace_id', $workspaceId)
                    ->where('status_key', $normalizedStatus)
                    ->first();

                if (!$targetColumn) {
                    $targetColumn = DB::table('kanban_columns')
                        ->where('workspace_id', $workspaceId)
                        ->where('is_backlog_column', true)
                        ->first();
                }

                if ($targetColumn) {
                    DB::table('tickets')
                        ->where('id', $ticket->id)
                        ->update([
                            'status' => $normalizedStatus,
                            'kanban_column_id' => $targetColumn->id,
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tickets') && Schema::hasColumn('tickets', 'kanban_column_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropConstrainedForeignId('kanban_column_id');
            });
        }

        Schema::dropIfExists('kanban_columns');

        DB::statement("
            ALTER TABLE tickets
            MODIFY status ENUM(
                'todo',
                'in_progress',
                'in_review',
                'done'
            ) NOT NULL DEFAULT 'todo'
        ");
    }
};