<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class ActivityLogService
{
    protected WorkspaceEmailNotificationService $workspaceEmailNotificationService;

    public function __construct(WorkspaceEmailNotificationService $workspaceEmailNotificationService)
    {
        $this->workspaceEmailNotificationService = $workspaceEmailNotificationService;
    }

    public function create(
        int $projectId,
        ?int $ticketId,
        ?int $userId,
        string $action,
        ?string $description = null
    ): ActivityLog {
        $activityLog = ActivityLog::create([
            'project_id' => $projectId,
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
        ]);

        try {
            $this->workspaceEmailNotificationService->sendActivityNotification($activityLog);
        } catch (\Throwable $error) {
            Log::error('Project activity email notification failed.', [
                'activity_log_id' => $activityLog->id,
                'project_id' => $projectId,
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'action' => $action,
                'error' => $error->getMessage(),
            ]);
        }

        return $activityLog;
    }

    public function getWorkspaceLogs(int $projectId)
    {
        return ActivityLog::where('project_id', $projectId)
            ->with([
                'user:id,name,email',
                'ticket:id,title,status,priority',
            ])
            ->latest()
            ->get();
    }

    public function getTicketLogs(int $ticketId)
    {
        return ActivityLog::where('ticket_id', $ticketId)
            ->with([
                'user:id,name,email',
                'ticket:id,title,status,priority',
            ])
            ->latest()
            ->get();
    }
}
