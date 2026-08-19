<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{

    // Xác định người dùng có quyền gửi yêu cầu hay không.
    public function authorize(): bool
    {
        return true;
    }

    // Trả về các quy tắc kiểm tra dữ liệu đầu vào.
    public function rules(): array
    {
        return [
            'full_name' => ['required_without:name', 'string', 'max:100'],
            'name' => ['required_without:full_name', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', Rule::unique('bstore_auth.users', 'email')],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('bstore_auth.users', 'phone')],
            'address' => ['nullable', 'string', 'max:500'],
            'province' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'ward' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date'],
            'avatar' => ['nullable', 'string', 'max:255'],
        ];
    }
}
