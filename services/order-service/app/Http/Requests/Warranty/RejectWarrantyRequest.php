<?php

namespace App\Http\Requests\Warranty;

use Illuminate\Foundation\Http\FormRequest;

class RejectWarrantyRequest extends FormRequest
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
            'rejection_reason' => ['required', 'string', 'min:5', 'max:2000', function ($attribute, $value, $fail): void {
                if (trim((string) $value) === '') {
                    $fail('Lý do từ chối không được chỉ chứa khoảng trắng');
                }
            }],
        ];
    }
}
