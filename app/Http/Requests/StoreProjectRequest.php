<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'project_key'  => 'nullable|string|max:10|alpha_num|uppercase',
            'project_type' => 'nullable|in:software,it_support,marketing,hr,construction,general',
            'project_mode' => 'nullable|in:kanban,scrum',
        ];
    }
}
