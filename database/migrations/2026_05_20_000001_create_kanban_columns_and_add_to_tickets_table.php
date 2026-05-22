<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kanban_columns')) {
            Schema::create('kanban_columns', function (Blueprint $table) {
                $table->id();

                $table->foreignId('workspace_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->string('name');
                $table->string('slug');
                $table->unsignedInteger('position')->default(1);

                /*
                |--------------------------------------------------------------------------
                | status_key
                |--------------------------------------------------------------------------
                | This keeps your old Planora logic safe.
                | Example:
                | Backlog column = todo
                | Done column = done
                |
                | Your dashboard/archive/activity code can still read ticket.status.
                */
                $table->string('status_key')->nullable();

                $table->boolean('is_backlog_column')->default(false);
                $table->boolean('is_done_column')->default(false);

                $table->timestamps();

                $table->unique(['workspace_id', 'slug']);
            });
        }

        if (Schema::hasTable('tickets') && !Schema::hasColumn('tickets', 'kanban_column_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->foreignId('kanban_column_id')
                    ->nullable()
                    ->after('workspace_id')
                    ->constrained('kanban_columns')
                    ->nullOnDelete();
            });
        }

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

            $columns = DB::table('kanban_columns')
                ->where('workspace_id', $workspaceId)
                ->get()
                ->keyBy('status_key');

            $tickets = DB::table('tickets')
                ->where('workspace_id', $workspaceId)
                ->whereNull('kanban_column_id')
                ->get();

            foreach ($tickets as $ticket) {
                $status = $ticket->status ?: 'todo';

                $targetColumn = $columns->get($status);

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
    }
};