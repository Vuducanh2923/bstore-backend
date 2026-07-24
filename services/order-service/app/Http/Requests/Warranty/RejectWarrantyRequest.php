<?php

namespace App\Http\Requests\Warranty;

use Illuminate\Foundation\Http\FormRequest;

class RejectWarrantyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:5', 'max:2000', function ($attribute, $value, $fail): void {
                if (trim((string) $value) === '') {
                    $fail('Ly do tu choi khong duoc chi chua khoang trang');
                }
            }],
        ];
    }
}
