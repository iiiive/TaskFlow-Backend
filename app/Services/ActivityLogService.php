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
        int $workspaceId,
        ?int $ticketId,
        ?int $userId,
        string $action,
        ?string $description = null
    ): ActivityLog {
        $activityLog = ActivityLog::create([
            'workspace_id' => $workspaceId,
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Email Notification Hook
        |--------------------------------------------------------------------------
        | Every time an activity log is created, we also notify workspace members.
        | This keeps the logic centralized. We do not need to add email code inside
        | TicketController, WorkspaceController, comments, epics, etc.
        */
        try {
            $this->workspaceEmailNotificationService->sendActivityNotification($activityLog);
        } catch (\Throwable $error) {
            /*
            |--------------------------------------------------------------------------
            | Important
            |--------------------------------------------------------------------------
            | We DO NOT want email failure to break the actual system action.
            | Example: if Brevo SMTP fails, ticket creation/update should still work.
            */
            Log::error('Workspace activity email notification failed.', [
                'activity_log_id' => $activityLog->id,
                'workspace_id' => $workspaceId,
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'action' => $action,
                'error' => $error->getMessage(),
            ]);
        }

        return $activityLog;
    }

    public function getWorkspaceLogs(int $workspaceId)
    {
        return ActivityLog::where('workspace_id', $workspaceId)
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