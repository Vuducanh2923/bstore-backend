<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['prohibited'],
            'role_id' => ['prohibited'],
            'full_name' => ['required_without:name', 'string', 'max:100'],
            'name' => ['required_without:full_name', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', Rule::unique('bstore_auth.users', 'email')],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('bstore_auth.users', 'phone')],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'avatar' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'blocked'])],
        ];
    }
}
