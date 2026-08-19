<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'blocked'])],
        ];
    }
}
