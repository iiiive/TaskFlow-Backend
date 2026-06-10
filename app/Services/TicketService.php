<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use App\Services\AssignmentEngineService;

class TicketService
{
    public function getWorkspaceTickets(Workspace $workspace, array $filters = []): Collection
    {
        $query = Ticket::where('project_id', $workspace->id)
            ->with([
                'creator:id,name,email',
                'assignee:id,name,email',
                'reporter:id,name,email',
                'kanbanColumn',
                'epic',
                'labels',
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

        if (!empty($filters['issue_type'])) {
            $query->where('issue_type', $filters['issue_type']);
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
                $q->whereRaw('title ILIKE ?', ['%' . $search . '%'])
                    ->orWhereRaw('description ILIKE ?', ['%' . $search . '%'])
                    ->orWhereRaw('issue_number ILIKE ?', ['%' . $search . '%']);
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
        // Auto-assign when no explicit assignee is provided
        if (empty($data['assigned_to'])) {
            $assignee = app(AssignmentEngineService::class)->resolveAssignee($workspace);
            if ($assignee) {
                $data['assigned_to'] = $assignee;
            }
        }

        $this->validateAssignee($workspace->id, $data['assigned_to'] ?? null);

        $issueNumber = $workspace->generateNextIssueNumber();

        $ticket = Ticket::create([
            'project_id' => $workspace->id,
            'kanban_column_id' => $data['kanban_column_id'] ?? null,
            'sprint_id' => $data['sprint_id'] ?? null,
            'issue_type' => $data['issue_type'] ?? 'task',
            'parent_ticket_id' => $data['parent_ticket_id'] ?? null,
            'issue_number' => $issueNumber,
            'created_by' => $userId,
            'reporter_id' => $data['reporter_id'] ?? $userId,
            'assigned_to' => $data['assigned_to'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'todo',
            'priority' => $data['priority'] ?? 'medium',
            'story_points' => $data['story_points'] ?? null,
            'category' => $data['category'] ?? null,
            'due_date' => $data['due_date'] ?? null,
        ]);

        return $ticket->load([
            'creator:id,name,email',
            'assignee:id,name,email',
            'reporter:id,name,email',
            'kanbanColumn',
            'epic',
            'labels',
        ]);
    }

    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        $this->validateAssignee($ticket->project_id, $data['assigned_to'] ?? null);

        $ticket->update([
            'kanban_column_id' => array_key_exists('kanban_column_id', $data)
                ? $data['kanban_column_id']
                : $ticket->kanban_column_id,

            'sprint_id' => array_key_exists('sprint_id', $data)
                ? $data['sprint_id']
                : $ticket->sprint_id,

            'issue_type' => $data['issue_type'] ?? $ticket->issue_type,

            'parent_ticket_id' => array_key_exists('parent_ticket_id', $data)
                ? $data['parent_ticket_id']
                : $ticket->parent_ticket_id,

            'title' => $data['title'] ?? $ticket->title,

            'description' => array_key_exists('description', $data)
                ? $data['description']
                : $ticket->description,

            'status' => $data['status'] ?? $ticket->status,

            'priority' => $data['priority'] ?? $ticket->priority,

            'reporter_id' => array_key_exists('reporter_id', $data)
                ? $data['reporter_id']
                : $ticket->reporter_id,

            'assigned_to' => array_key_exists('assigned_to', $data)
                ? $data['assigned_to']
                : $ticket->assigned_to,

            'story_points' => array_key_exists('story_points', $data)
                ? $data['story_points']
                : $ticket->story_points,

            'category' => array_key_exists('category', $data)
                ? $data['category']
                : $ticket->category,

            'due_date' => array_key_exists('due_date', $data)
                ? $data['due_date']
                : $ticket->due_date,
        ]);

        return $ticket->load([
            'creator:id,name,email',
            'assignee:id,name,email',
            'reporter:id,name,email',
            'kanbanColumn',
            'epic',
            'labels',
        ]);
    }

    public function deleteTicket(Ticket $ticket): void
    {
        $ticket->delete();
    }

    private function validateAssignee(int $projectId, ?int $assignedUserId): void
    {
        if (!$assignedUserId) {
            return;
        }

        $isAssignedUserMember = WorkspaceMember::where('project_id', $projectId)
            ->where('user_id', $assignedUserId)
            ->exists();

        if (!$isAssignedUserMember) {
            throw ValidationException::withMessages([
                'assigned_to' => ['Assigned user must be a member of this project.'],
            ]);
        }
    }
}
