<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Models\WorkspaceMember;

class AttachmentPolicy
{
    public function view(User $user, TicketAttachment $attachment): bool
    {
        return WorkspaceMember::where('project_id', $attachment->ticket->project_id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Whether the user may view attachments on a given ticket (any member).
     */
    public function viewAny(User $user, Ticket $ticket): bool
    {
        return WorkspaceMember::where('project_id', $ticket->project_id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function create(User $user, Ticket $ticket): bool
    {
        $role = WorkspaceMember::where('project_id', $ticket->project_id)
            ->where('user_id', $user->id)
            ->value('role');

        return in_array($role, WorkspaceMember::ROLES_CAN_EDIT, true);
    }

    public function delete(User $user, TicketAttachment $attachment): bool
    {
        if ($user->id === $attachment->user_id) {
            return true;
        }

        $role = WorkspaceMember::where('project_id', $attachment->ticket->project_id)
            ->where('user_id', $user->id)
            ->value('role');

        return in_array($role, WorkspaceMember::ROLES_CAN_DELETE, true);
    }
}
