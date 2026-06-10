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
            'id'                => $this->id,
            'project_id'        => $this->project_id,
            'kanban_column_id'  => $this->kanban_column_id,
            'epic_id'           => $this->epic_id,
            'sprint_id'         => $this->sprint_id,
            'workflow_state_id' => $this->workflow_state_id,
            'issue_type'        => $this->issue_type,
            'parent_ticket_id'  => $this->parent_ticket_id,
            'issue_number'      => $this->issue_number,
            'created_by'        => $this->created_by,
            'reporter_id'       => $this->reporter_id,
            'assigned_to'       => $this->assigned_to,

            'title' => $this->title,
            'description' => $this->description,

            'status' => $this->status,
            'priority' => $this->priority,
            'story_points' => $this->story_points,
            'category' => $this->category,
            'due_date' => $this->due_date,

            'due_date_warning' => $insightService->getDueDateWarning($this->resource),
            'suggested_priority' => $insightService->suggestPriority($this->resource),

            'kanban_column'  => new KanbanColumnResource($this->whenLoaded('kanbanColumn')),
            'epic'           => new EpicResource($this->whenLoaded('epic')),
            'sprint'         => new SprintResource($this->whenLoaded('sprint')),
            'workflow_state' => new WorkflowStateResource($this->whenLoaded('workflowState')),
            'parent'         => new self($this->whenLoaded('parent')),
            'children'       => self::collection($this->whenLoaded('children')),
            'labels'         => LabelResource::collection($this->whenLoaded('labels')),

            'creator'  => new UserResource($this->whenLoaded('creator')),
            'reporter' => new UserResource($this->whenLoaded('reporter')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
