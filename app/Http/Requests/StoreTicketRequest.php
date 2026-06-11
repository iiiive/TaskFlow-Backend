<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statuses = implode(',', [
            'todo', 'ready_for_development', 'dev_in_progress',
            'ready_for_testing', 'ready_for_uat', 'done', 'completed',
        ]);

        return [
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'issue_type'      => 'nullable|in:' . implode(',', Ticket::ISSUE_TYPES),
            'parent_ticket_id'=> 'nullable|integer|exists:tickets,id',
            'status'          => "nullable|in:{$statuses}",
            'kanban_column_id'=> 'nullable|integer|exists:kanban_columns,id',
            'epic_id'         => 'nullable|integer|exists:epics,id',
            'sprint_id'       => 'nullable|integer|exists:sprints,id',
            'priority'        => 'nullable|in:low,medium,high,urgent',
            'reporter_id'     => 'nullable|exists:users,id',
            'assigned_to'     => 'nullable|exists:users,id',
            'story_points'    => 'nullable|integer|min:0|max:100',
            'category'        => 'nullable|string|max:100',
            'due_date'        => 'nullable|date',
            'label_ids'       => 'nullable|array',
            'label_ids.*'     => 'integer|exists:labels,id',
        ];
    }
}
