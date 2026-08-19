<?php

namespace App\Http\Requests\Warranty;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarrantyRequest extends FormRequest
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
            'order_id' => ['required', 'integer', 'min:1'],
            'order_item_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000', function ($attribute, $value, $fail): void {
                if (trim((string) $value) === '') {
                    $fail('Lý do bảo hành không được chỉ chứa khoảng trắng');
                }
            }],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
