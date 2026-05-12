<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TicketService
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function getWorkspaceTickets(Workspace $workspace, array $filters = []): Collection
    {
        $query = Ticket::where('workspace_id', $workspace->id)
            ->with([
                'creator:id,name,email',
                'assignee:id,name,email',
            ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['search'] . '%')
                ->orWhere('description', 'like', '%' . $filters['search'] . '%');
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

        // This records that a ticket was created.
        $this->activityLogService->create(
            $workspace->id,
            $ticket->id,
            $userId,
            'ticket_created',
            'A new ticket was created.'
        );

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

        // This records that a ticket was updated.
        $this->activityLogService->create(
            $ticket->workspace_id,
            $ticket->id,
            auth()->id(),
            'ticket_updated',
            'Ticket details were updated.'
        );

        return $ticket->load([
            'creator:id,name,email',
            'assignee:id,name,email',
        ]);
    }

    public function deleteTicket(Ticket $ticket): void
    {
        // This records that a ticket was deleted before the ticket is removed.
        $this->activityLogService->create(
            $ticket->workspace_id,
            $ticket->id,
            auth()->id(),
            'ticket_deleted',
            'A ticket was deleted.'
        );

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