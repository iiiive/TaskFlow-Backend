<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Workspace extends Model
{
    use HasFactory;

    protected $table = 'projects';

    protected $fillable = [
        'owner_id',
        'organization_id',
        'name',
        'description',
        'project_key',
        'project_type',
        'project_mode',
        'is_template',
        'last_issue_number',
        'archived_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'last_issue_number' => 'integer',
        'is_template' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'project_members', 'project_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function workspaceMembers()
    {
        return $this->hasMany(WorkspaceMember::class, 'project_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'project_id');
    }

    public function epics()
    {
        return $this->hasMany(Epic::class, 'project_id');
    }

    public function kanbanColumns()
    {
        return $this->hasMany(KanbanColumn::class, 'project_id')->orderBy('position');
    }

    public function backlogColumn()
    {
        return $this->hasOne(KanbanColumn::class, 'project_id')
            ->where('is_backlog_column', true);
    }

    public function doneColumn()
    {
        return $this->hasOne(KanbanColumn::class, 'project_id')
            ->where('is_done_column', true);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class, 'project_id');
    }

    public function timeLogs()
    {
        return $this->hasMany(TicketTimeLog::class, 'project_id');
    }

    public function labels()
    {
        return $this->hasMany(Label::class, 'project_id');
    }

    public function workflowTemplates()
    {
        return $this->hasMany(WorkflowTemplate::class, 'project_id');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function generateNextIssueNumber(): string
    {
        $number = DB::table('projects')
            ->where('id', $this->id)
            ->lockForUpdate()
            ->value('last_issue_number') + 1;

        DB::table('projects')
            ->where('id', $this->id)
            ->update(['last_issue_number' => $number]);

        $key = $this->project_key ?? 'PROJ';

        return $key . '-' . $number;
    }

    public function createDefaultKanbanColumns(): void
    {
        if ($this->kanbanColumns()->exists()) {
            return;
        }

        $columns = [
            [
                'name' => 'Backlog',
                'status_key' => 'todo',
                'is_backlog_column' => true,
                'is_done_column' => false,
            ],
            [
                'name' => 'Ready for Development',
                'status_key' => 'ready_for_development',
                'is_backlog_column' => false,
                'is_done_column' => false,
            ],
            [
                'name' => 'Dev In Progress',
                'status_key' => 'dev_in_progress',
                'is_backlog_column' => false,
                'is_done_column' => false,
            ],
            [
                'name' => 'Ready for Testing',
                'status_key' => 'ready_for_testing',
                'is_backlog_column' => false,
                'is_done_column' => false,
            ],
            [
                'name' => 'Ready for UAT',
                'status_key' => 'ready_for_uat',
                'is_backlog_column' => false,
                'is_done_column' => false,
            ],
            [
                'name' => 'Done',
                'status_key' => 'done',
                'is_backlog_column' => false,
                'is_done_column' => true,
            ],
        ];

        foreach ($columns as $index => $column) {
            $this->kanbanColumns()->create([
                'name' => $column['name'],
                'slug' => Str::slug($column['name']),
                'position' => $index + 1,
                'status_key' => $column['status_key'],
                'is_backlog_column' => $column['is_backlog_column'],
                'is_done_column' => $column['is_done_column'],
            ]);
        }
    }
}
