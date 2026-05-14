<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $avatarUrl = null;

        if ($this->avatar) {
            if (
                str_starts_with($this->avatar, 'http://') ||
                str_starts_with($this->avatar, 'https://')
            ) {
                $avatarUrl = $this->avatar;
            } else {
                $avatarUrl = url('storage/' . $this->avatar);
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'google_id' => $this->google_id,
            'has_password' => !empty($this->password),
            'avatar' => $this->avatar,
            'avatar_url' => $avatarUrl,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}