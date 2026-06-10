<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowStateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'workflow_template_id' => $this->workflow_template_id,
            'name'                 => $this->name,
            'color'                => $this->color,
            'position'             => $this->position,
            'is_initial'           => $this->is_initial,
            'is_final'             => $this->is_final,
            'requires_approval'    => $this->requires_approval,
            'created_at'           => $this->created_at?->toISOString(),
        ];
    }
}
