<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'description',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function workspaceMembers()
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function epics()
    {
        return $this->hasMany(Epic::class);
    }

    public function kanbanColumns()
    {
        return $this->hasMany(KanbanColumn::class)->orderBy('position');
    }

    public function backlogColumn()
    {
        return $this->hasOne(KanbanColumn::class)
            ->where('is_backlog_column', true);
    }

    public function doneColumn()
    {
        return $this->hasOne(KanbanColumn::class)
            ->where('is_done_column', true);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function timeLogs()
    {
        return $this->hasMany(TicketTimeLog::class);
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