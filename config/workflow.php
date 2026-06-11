<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ticket fields that a workflow state may require before entry
    |--------------------------------------------------------------------------
    |
    | Keys are the ticket attribute checked for "presence"; labels are for the UI.
    |
    */
    'required_field_options' => [
        'assigned_to'  => 'Assignee',
        'due_date'     => 'Due Date',
        'story_points' => 'Story Points',
        'description'  => 'Description',
        'epic_id'      => 'Epic',
        'priority'     => 'Priority',
    ],
];
