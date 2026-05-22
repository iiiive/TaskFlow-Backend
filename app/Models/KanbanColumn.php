<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KanbanColumn extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'position',
        'status_key',
        'is_backlog_column',
        'is_done_column',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_backlog_column' => 'boolean',
        'is_done_column' => 'boolean',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'kanban_column_id');
    }
}