<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkspaceMember extends Model
{
    use HasFactory;

    protected $table = 'project_members';

    const ROLES = [
        'project_manager',
        'team_lead',
        'developer',
        'tester',
        'viewer',
        'client',
    ];

    // project_manager and team_lead have full project control.
    const ROLES_CAN_EDIT = ['project_manager', 'team_lead', 'developer', 'tester'];
    const ROLES_CAN_DELETE = ['project_manager', 'team_lead', 'developer', 'tester'];
    const ROLES_CAN_MANAGE_MEMBERS = ['project_manager', 'team_lead'];
    const ROLES_CAN_MANAGE_PROJECT = ['project_manager', 'team_lead'];

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
