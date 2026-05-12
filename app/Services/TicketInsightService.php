<?php

namespace App\Services;

use App\Models\Ticket;
use Carbon\Carbon;

class TicketInsightService
{
    public function getDueDateWarning(Ticket $ticket): string
    {
        if (!$ticket->due_date) {
            return 'no_due_date';
        }

        if ($ticket->status === 'done') {
            return 'completed';
        }

        $today = Carbon::today();
        $dueDate = Carbon::parse($ticket->due_date)->startOfDay();

        if ($dueDate->lt($today)) {
            return 'overdue';
        }

        if ($dueDate->isSameDay($today)) {
            return 'due_today';
        }

        if ($dueDate->diffInDays($today) <= 3) {
            return 'due_soon';
        }

        return 'normal';
    }

    public function suggestPriority(Ticket $ticket): string
    {
        $warning = $this->getDueDateWarning($ticket);

        if ($ticket->status === 'done') {
            return $ticket->priority;
        }

        return match ($warning) {
            'overdue' => 'urgent',
            'due_today' => 'urgent',
            'due_soon' => 'high',
            'no_due_date' => 'medium',
            default => $ticket->priority,
        };
    }

    public function getTicketInsights(Ticket $ticket): array
    {
        return [
            'due_date_warning' => $this->getDueDateWarning($ticket),
            'suggested_priority' => $this->suggestPriority($ticket),
        ];
    }
}