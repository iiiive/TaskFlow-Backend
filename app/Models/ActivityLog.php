<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'ticket_id',
        'user_id',
        'action',
        'description',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class, 'project_id');
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
