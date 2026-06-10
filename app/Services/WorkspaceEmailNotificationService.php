<?php

namespace App\Services;

use App\Mail\WorkspaceActivityNotificationMail;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WorkspaceEmailNotificationService
{
    public function sendActivityNotification(ActivityLog $activityLog): void
    {
        $activityLog->load([
            'workspace.owner:id,name,email',
            'workspace.workspaceMembers.user:id,name,email',
            'user:id,name,email',
            'ticket:id,project_id,kanban_column_id,title,status,priority,due_date',
            'ticket.kanbanColumn:id,project_id,name,slug,status_key,is_backlog_column,is_done_column',
        ]);

        if (!$activityLog->workspace) {
            return;
        }

        if (!filter_var(env('WORKSPACE_ACTIVITY_EMAIL_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $recipients = $this->getWorkspaceRecipients($activityLog);

        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient->email)->send(
                    new WorkspaceActivityNotificationMail($activityLog, $recipient)
                );
            } catch (\Throwable $error) {
                Log::error('Failed to send project activity email.', [
                    'activity_log_id' => $activityLog->id,
                    'recipient_id' => $recipient->id,
                    'recipient_email' => $recipient->email,
                    'error' => $error->getMessage(),
                ]);
            }
        }
    }

    private function getWorkspaceRecipients(ActivityLog $activityLog): Collection
    {
        $workspace = $activityLog->workspace;

        $members = $workspace->workspaceMembers
            ->map(fn ($member) => $member->user)
            ->filter(fn ($user) => $user instanceof User)
            ->filter(fn ($user) => !empty($user->email));

        if ($workspace->owner && !empty($workspace->owner->email)) {
            $members->push($workspace->owner);
        }

        $members = $members->unique('email')->values();

        $includeActor = filter_var(
            env('WORKSPACE_ACTIVITY_EMAIL_INCLUDE_ACTOR', false),
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$includeActor && $activityLog->user_id) {
            $members = $members
                ->reject(fn ($user) => (int) $user->id === (int) $activityLog->user_id)
                ->values();
        }

        return $members;
    }
}
