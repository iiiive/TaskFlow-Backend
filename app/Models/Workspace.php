<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}