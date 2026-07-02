<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $staffId = (int) $this->route('id');

        return [
            'role' => ['prohibited'],
            'role_id' => ['prohibited'],
            'full_name' => ['sometimes', 'required', 'string', 'max:100'],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:191', Rule::unique('bstore_auth.users', 'email')->ignore($staffId)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', Rule::unique('bstore_auth.users', 'phone')->ignore($staffId)],
            'password' => ['sometimes', 'nullable', 'string', 'min:6', 'max:255'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'blocked'])],
        ];
    }
}
