<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:191'],
            'otp_code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:6', 'max:255', 'confirmed'],
        ];
    }
}
