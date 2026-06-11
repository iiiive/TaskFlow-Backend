<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    const ISSUE_TYPES = [
        'epic', 'story', 'task', 'subtask', 'bug',
        'improvement', 'change_request', 'incident',
        'service_request', 'feature_request', 'other',
    ];

    protected $fillable = [
        'project_id',
        'kanban_column_id',
        'epic_id',
        'sprint_id',
        'workflow_state_id',
        'issue_type',
        'parent_ticket_id',
        'issue_number',
        'created_by',
        'reporter_id',
        'assigned_to',
        'title',
        'description',
        'status',
        'priority',
        'story_points',
        'category',
        'due_date',
    ];

    protected $casts = [
        'story_points' => 'integer',
        'due_date' => 'date',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class, 'project_id');
    }

    public function kanbanColumn()
    {
        return $this->belongsTo(KanbanColumn::class, 'kanban_column_id');
    }

    public function epic()
    {
        return $this->belongsTo(Epic::class);
    }

    public function sprint()
    {
        return $this->belongsTo(Sprint::class);
    }

    public function workflowState()
    {
        return $this->belongsTo(WorkflowState::class, 'workflow_state_id');
    }

    public function parent()
    {
        return $this->belongsTo(Ticket::class, 'parent_ticket_id');
    }

    public function children()
    {
        return $this->hasMany(Ticket::class, 'parent_ticket_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function labels()
    {
        return $this->belongsToMany(Label::class, 'issue_labels');
    }

    public function comments()
    {
        return $this->hasMany(TicketComment::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function timeLogs()
    {
        return $this->hasMany(TicketTimeLog::class);
    }
}
