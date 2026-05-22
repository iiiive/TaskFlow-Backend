<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TicketService
{
    public function getWorkspaceTickets(Workspace $workspace, array $filters = []): Collection
    {
        $query = Ticket::where('workspace_id', $workspace->id)
            ->with([
                'creator:id,name,email',
                'assignee:id,name,email',
                'kanbanColumn',
                'epic',
            ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['kanban_column_id'])) {
            $query->where('kanban_column_id', $filters['kanban_column_id']);
        }

        if (!empty($filters['epic_id'])) {
            $query->where('epic_id', $filters['epic_id']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['due_before'])) {
            $query->whereDate('due_date', '<=', $filters['due_before']);
        }

        if (!empty($filters['due_after'])) {
            $query->whereDate('due_date', '>=', $filters['due_after']);
        }

        return $query->latest()->get();
    }

    public function createTicket(Workspace $workspace, int $userId, array $data): Ticket
    {
        $this->validateAssignee($workspace->id, $data['assigned_to'] ?? null);

        /*
        |--------------------------------------------------------------------------
        | Important Fix
        |--------------------------------------------------------------------------
        | This service only creates the ticket.
        | It does NOT create activity logs or send emails anymore.
        |
        | Why?
        | TicketController already creates the accurate activity log/email using
        | the real Kanban column names. Keeping old logging here causes duplicate
        | emails and old wrong descriptions.
        */
        $ticket = Ticket::create([
            'workspace_id' => $workspace->id,
            'kanban_column_id' => $data['kanban_column_id'] ?? null,
            'created_by' => $userId,
            'assigned_to' => $data['assigned_to'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'todo',
            'priority' => $data['priority'] ?? 'medium',
            'due_date' => $data['due_date'] ?? null,
        ]);

        return $ticket->load([
            'creator:id,name,email',
            'assignee:id,name,email',
            'kanbanColumn',
            'epic',
        ]);
    }

    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        $this->validateAssignee($ticket->workspace_id, $data['assigned_to'] ?? null);

        /*
        |--------------------------------------------------------------------------
        | Important Fix
        |--------------------------------------------------------------------------
        | This service only updates the ticket.
        | It does NOT create the old "ticket_updated" activity anymore.
        |
        | The accurate activity logs/emails are now handled by TicketController:
        | - ticket_moved
        | - ticket_assignee_updated
        | - ticket_priority_updated
        | - ticket_title_updated
        | - ticket_description_updated
        | - ticket_due_date_updated
        | - ticket_epic_updated
        */
        $ticket->update([
            'kanban_column_id' => array_key_exists('kanban_column_id', $data)
                ? $data['kanban_column_id']
                : $ticket->kanban_column_id,

            'title' => $data['title'] ?? $ticket->title,

            'description' => array_key_exists('description', $data)
                ? $data['description']
                : $ticket->description,

            'status' => $data['status'] ?? $ticket->status,

            'priority' => $data['priority'] ?? $ticket->priority,

            'assigned_to' => array_key_exists('assigned_to', $data)
                ? $data['assigned_to']
                : $ticket->assigned_to,

            'due_date' => array_key_exists('due_date', $data)
                ? $data['due_date']
                : $ticket->due_date,
        ]);

        return $ticket->load([
            'creator:id,name,email',
            'assignee:id,name,email',
            'kanbanColumn',
            'epic',
        ]);
    }

    public function deleteTicket(Ticket $ticket): void
    {
        /*
        |--------------------------------------------------------------------------
        | Important Fix
        |--------------------------------------------------------------------------
        | No activity log here anymore.
        | TicketController handles the accurate ticket_deleted activity/email.
        */
        $ticket->delete();
    }

    private function validateAssignee(int $workspaceId, ?int $assignedUserId): void
    {
        if (!$assignedUserId) {
            return;
        }

        $isAssignedUserMember = WorkspaceMember::where('workspace_id', $workspaceId)
            ->where('user_id', $assignedUserId)
            ->exists();

        if (!$isAssignedUserMember) {
            throw ValidationException::withMessages([
                'assigned_to' => ['Assigned user must be a member of this workspace.'],
            ]);
        }
    }
}