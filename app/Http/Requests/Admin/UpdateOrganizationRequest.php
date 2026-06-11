<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_super_admin ?? false);
    }

    public function rules(): array
    {
        $orgId = $this->route('id');

        return [
            'name'                 => 'sometimes|required|string|max:255',
            'owner_email'          => 'sometimes|required|email|max:255',
            'subscription_plan_id' => 'sometimes|required|exists:subscription_plans,id',
            'is_active'            => 'sometimes|boolean',
            'primary_color'       => 'nullable|string|max:20',
            'custom_domain'       => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))+$/',
                Rule::unique('organizations', 'custom_domain')->ignore($orgId),
            ],
            'logo'                => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'custom_domain.regex' => 'Please enter a valid domain (e.g. acme.example.com).',
        ];
    }
}
