<?php

namespace App\Http\Requests\Warranty;

use Illuminate\Foundation\Http\FormRequest;

class WarrantyNoteRequest extends FormRequest
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
            'processing_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
