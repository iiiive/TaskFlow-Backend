<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketTimeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'ticket_id',
        'user_id',
        'hours',
        'description',
        'work_date',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
        'work_date' => 'date',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}