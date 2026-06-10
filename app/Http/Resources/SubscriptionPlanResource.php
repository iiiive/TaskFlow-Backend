<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'max_projects' => $this->max_projects,
            'max_members' => $this->max_members,
            'storage_gb' => $this->storage_gb,
            'features' => $this->features,
            'is_active' => $this->is_active,
            'organizations_count' => $this->when(
                isset($this->organizations_count),
                $this->organizations_count
            ),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
