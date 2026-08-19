<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
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
            'receiver_name' => ['required', 'string', 'max:100'],
            'receiver_phone' => ['required', 'string', 'max:30'],
            'receiver_email' => ['nullable', 'email', 'max:191'],
            'address' => ['required', 'string', 'max:500'],
            'province' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'ward' => ['nullable', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
