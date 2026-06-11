<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'project_id'  => 'nullable|integer|exists:projects,id',
        ];
    }
}
