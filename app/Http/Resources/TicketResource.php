<?php

namespace App\Http\Resources;

use App\Services\TicketInsightService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $insightService = app(TicketInsightService::class);

        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'kanban_column_id' => $this->kanban_column_id,
            'epic_id' => $this->epic_id,
            'created_by' => $this->created_by,
            'assigned_to' => $this->assigned_to,

            'title' => $this->title,
            'description' => $this->description,

            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->due_date,

            'due_date_warning' => $insightService->getDueDateWarning($this->resource),
            'suggested_priority' => $insightService->suggestPriority($this->resource),

            'kanban_column' => new KanbanColumnResource(
                $this->whenLoaded('kanbanColumn')
            ),

            'epic' => new EpicResource(
                $this->whenLoaded('epic')
            ),

            'creator' => new UserResource($this->whenLoaded('creator')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}