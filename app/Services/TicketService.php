<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TicketService
{
    public function getWorkspaceTickets(Workspace $workspace): Collection
    {
        return Ticket::where('workspace_id', $workspace->id)
            ->with([
                'creator:id,name,email',
                'assignee:id,name,email',
            ])
            ->latest()
            ->get();
    }

    public function createTicket(Workspace $workspace, int $userId, array $data): Ticket
    {
        $this->validateAssignee($workspace->id, $data['assigned_to'] ?? null);

        $ticket = Ticket::create([
            'workspace_id' => $workspace->id,
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
        ]);
    }

    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        $this->validateAssignee($ticket->workspace_id, $data['assigned_to'] ?? null);

        $ticket->update([
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
        ]);
    }

    public function deleteTicket(Ticket $ticket): void
    {
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
                'assigned_to' => ['Assigned user must be a member of this workspace.']
            ]);
        }
    }
}