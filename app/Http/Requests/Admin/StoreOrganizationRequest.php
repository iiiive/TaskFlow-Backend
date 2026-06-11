<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_super_admin ?? false;
    }

    public function rules(): array
    {
        return [
            'name'                 => 'required|string|max:255',
            'owner_email'          => 'required|email|max:255',
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
        ];
    }
}
