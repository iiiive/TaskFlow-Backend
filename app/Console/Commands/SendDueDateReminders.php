<?php

namespace App\Console\Commands;

use App\Mail\TicketDueTomorrowMail;
use App\Mail\TicketOverdueMail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendDueDateReminders extends Command
{
    protected $signature   = 'planora:send-due-date-reminders';
    protected $description = 'Send due-tomorrow and overdue ticket email reminders';

    public function handle(): int
    {
        $tomorrow = Carbon::tomorrow()->toDateString();
        $today    = Carbon::today()->toDateString();

        $this->sendDueTomorrowReminders($tomorrow);
        $this->sendOverdueReminders($today);

        $this->info('Due-date reminders dispatched.');

        return self::SUCCESS;
    }

    private function sendDueTomorrowReminders(string $tomorrow): void
    {
        Ticket::whereDate('due_date', $tomorrow)
            ->whereNotIn('status', ['done', 'completed'])
            ->with(['assignee', 'reporter', 'workspace'])
            ->chunkById(100, function ($tickets) {
                foreach ($tickets as $ticket) {
                    $recipients = collect();

                    if ($ticket->assignee) $recipients->push($ticket->assignee);
                    if ($ticket->reporter && $ticket->reporter->id !== $ticket->assignee?->id) {
                        $recipients->push($ticket->reporter);
                    }

                    foreach ($recipients as $user) {
                        Mail::to($user->email)->queue(new TicketDueTomorrowMail($user, $ticket));
                    }
                }
            });
    }

    private function sendOverdueReminders(string $today): void
    {
        Ticket::whereDate('due_date', '<', $today)
            ->whereNotIn('status', ['done', 'completed'])
            ->with(['assignee', 'reporter', 'workspace', 'workspace.workspaceMembers.user'])
            ->chunkById(100, function ($tickets) {
                foreach ($tickets as $ticket) {
                    $daysOverdue = (int) Carbon::parse($ticket->due_date)->diffInDays(now());

                    $projectManagers = collect();
                    if ($ticket->workspace) {
                        $projectManagers = $ticket->workspace->workspaceMembers
                            ->filter(fn($m) => in_array($m->role, ['owner', 'admin', 'project_manager']))
                            ->map(fn($m) => $m->user)
                            ->filter();
                    }

                    $recipients = collect();
                    if ($ticket->assignee) $recipients->push($ticket->assignee);
                    $recipients = $recipients->merge($projectManagers)->unique('id');

                    foreach ($recipients as $user) {
                        Mail::to($user->email)->queue(
                            new TicketOverdueMail($user, $ticket, $daysOverdue)
                        );
                    }
                }
            });
    }
}
