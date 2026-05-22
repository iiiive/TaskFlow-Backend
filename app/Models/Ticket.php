<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'kanban_column_id',
        'epic_id',
        'created_by',
        'assigned_to',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function kanbanColumn()
    {
        return $this->belongsTo(KanbanColumn::class, 'kanban_column_id');
    }

    public function epic()
    {
        return $this->belongsTo(Epic::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
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