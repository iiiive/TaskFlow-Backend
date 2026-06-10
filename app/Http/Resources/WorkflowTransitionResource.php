<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowTransitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'workflow_template_id' => $this->workflow_template_id,
            'from_state_id'        => $this->from_state_id,
            'to_state_id'          => $this->to_state_id,
            'name'                 => $this->name,
            'from_state'           => new WorkflowStateResource($this->whenLoaded('fromState')),
            'to_state'             => new WorkflowStateResource($this->whenLoaded('toState')),
        ];
    }
}
