<?php

namespace App\Services;

use App\Models\ActivityLog;

class ActivityLogService
{
    public function create(
        int $workspaceId,
        ?int $ticketId,
        ?int $userId,
        string $action,
        ?string $description = null
    ): ActivityLog {
        return ActivityLog::create([
            'workspace_id' => $workspaceId,
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
        ]);
    }

    public function getWorkspaceLogs(int $workspaceId)
    {
        return ActivityLog::where('workspace_id', $workspaceId)
            ->with('user:id,name,email')
            ->latest()
            ->get();
    }

    public function getTicketLogs(int $ticketId)
    {
        return ActivityLog::where('ticket_id', $ticketId)
            ->with('user:id,name,email')
            ->latest()
            ->get();
    }
}