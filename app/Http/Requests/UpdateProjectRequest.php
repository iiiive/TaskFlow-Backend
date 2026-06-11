<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => 'sometimes|required|string|max:255',
            'description'  => 'nullable|string',
            'project_type' => 'nullable|in:software,it_support,marketing,hr,construction,general',
            'project_mode' => 'nullable|in:kanban,scrum',
        ];
    }
}
