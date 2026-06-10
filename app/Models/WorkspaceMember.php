<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkspaceMember extends Model
{
    use HasFactory;

    protected $table = 'project_members';

    const ROLES = [
        'owner',
        'admin',
        'project_manager',
        'team_lead',
        'developer',
        'tester',
        'viewer',
        'client',
    ];

    const ROLES_CAN_EDIT = ['owner', 'admin', 'project_manager', 'team_lead', 'developer'];
    const ROLES_CAN_DELETE = ['owner', 'admin', 'project_manager'];
    const ROLES_CAN_MANAGE_MEMBERS = ['owner', 'admin'];

    protected $fillable = [
        'project_id',
        'user_id',
        'role',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class, 'project_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
