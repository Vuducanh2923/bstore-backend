<?php

namespace App\Http\Requests\Profile;

class UpdateAddressRequest extends StoreAddressRequest
{
    public function rules(): array
    {
        return [
            'receiver_name' => ['sometimes', 'required', 'string', 'max:100'],
            'receiver_phone' => ['sometimes', 'required', 'string', 'max:30'],
            'receiver_email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'address' => ['sometimes', 'required', 'string', 'max:500'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'district' => ['sometimes', 'nullable', 'string', 'max:100'],
            'ward' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
