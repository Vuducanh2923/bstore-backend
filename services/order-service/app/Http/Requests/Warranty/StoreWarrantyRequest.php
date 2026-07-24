<?php

namespace App\Http\Requests\Warranty;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarrantyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'min:1'],
            'order_item_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000', function ($attribute, $value, $fail): void {
                if (trim((string) $value) === '') {
                    $fail('Ly do bao hanh khong duoc chi chua khoang trang');
                }
            }],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
