<?php

namespace App\Http\Requests\Discount;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiscountRequest extends FormRequest
{

    // Thực hiện prepare cho kiểm tra dữ liệu.
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    // Xác định người dùng có quyền gửi yêu cầu hay không.
    public function authorize(): bool
    {
        return true;
    }

    // Trả về các quy tắc kiểm tra dữ liệu đầu vào.
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:191', 'regex:/^\S+$/'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed_amount'])],
            'discount_value' => [
                'required',
                'numeric',
                'gt:0',
                Rule::when($this->input('discount_type') === 'percentage', ['lte:100']),
            ],
            'max_discount_amount' => ['nullable', 'numeric', 'gt:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['required', 'date', 'before:ends_at'],
            'ends_at' => ['required', 'date', 'after:starts_at', 'after_or_equal:now'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'expired'])],
        ];
    }
}
