<?php

namespace App\Mail;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkspaceActivityNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public ActivityLog $activityLog;
    public User $recipient;

    public function __construct(ActivityLog $activityLog, User $recipient)
    {
        /*
        |--------------------------------------------------------------------------
        | Load needed relationships
        |--------------------------------------------------------------------------
        | This makes sure the email can safely access:
        | - workspace name
        | - actor/user name
        | - ticket title/status/priority
        |
        | Without this, some values may become null depending on how the activity
        | log was queried before sending the email.
        */
        $this->activityLog = $activityLog->loadMissing([
            'workspace',
            'ticket',
            'user',
        ]);

        $this->recipient = $recipient;
    }

    public function build()
    {
        $workspaceName = $this->activityLog->workspace?->name ?? 'Planora Workspace';

        $actionTitle = $this->formatActionTitle($this->activityLog->action);

        $activityDescription = $this->activityLog->description
            ?? 'A workspace activity was recorded.';

        $actorName = $this->activityLog->user?->name
            ?? 'A workspace member';

        $ticketTitle = $this->activityLog->ticket?->title;

        $workspaceUrl = $this->buildWorkspaceUrl();
        $ticketUrl = $this->buildTicketUrl();

        return $this
            ->subject('New activity in ' . $workspaceName . ': ' . $actionTitle)
            ->view('emails.workspace-activity')
            ->with([
                /*
                |--------------------------------------------------------------------------
                | Main objects
                |--------------------------------------------------------------------------
                */
                'activityLog' => $this->activityLog,
                'recipient' => $this->recipient,

                /*
                |--------------------------------------------------------------------------
                | Simple email display values
                |--------------------------------------------------------------------------
                | These are the names your Blade template is currently using:
                | $workspaceName
                | $action
                | $description
                | $actorName
                | $ticketTitle
                */
                'workspaceName' => $workspaceName,
                'action' => $actionTitle,
                'description' => $activityDescription,
                'actorName' => $actorName,
                'ticketTitle' => $ticketTitle,

                /*
                |--------------------------------------------------------------------------
                | Extra values
                |--------------------------------------------------------------------------
                | These are kept in case your email template uses the more detailed
                | variable names too.
                */
                'actionTitle' => $actionTitle,
                'activityDescription' => $activityDescription,
                'ticketStatus' => $this->activityLog->ticket?->status,
                'ticketPriority' => $this->activityLog->ticket?->priority,
                'activityDate' => $this->activityLog->created_at?->format('F d, Y h:i A'),
                'workspaceUrl' => $workspaceUrl,
                'ticketUrl' => $ticketUrl,

                /*
                |--------------------------------------------------------------------------
                | Compatibility with your older Blade button variable
                |--------------------------------------------------------------------------
                */
                'frontendUrl' => $workspaceUrl,
            ]);
    }

    private function formatActionTitle(?string $action): string
    {
        if (!$action) {
            return 'Workspace Activity';
        }

        return str($action)
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();
    }

    private function buildWorkspaceUrl(): ?string
    {
        $frontendUrl = rtrim((string) env('FRONTEND_URL', ''), '/');

        if (!$frontendUrl || !$this->activityLog->workspace_id) {
            return null;
        }

        return $frontendUrl . '/workspaces/' . $this->activityLog->workspace_id . '/board';
    }

    private function buildTicketUrl(): ?string
    {
        $frontendUrl = rtrim((string) env('FRONTEND_URL', ''), '/');

        if (!$frontendUrl || !$this->activityLog->ticket_id) {
            return null;
        }

        return $frontendUrl . '/tickets/' . $this->activityLog->ticket_id;
    }
}