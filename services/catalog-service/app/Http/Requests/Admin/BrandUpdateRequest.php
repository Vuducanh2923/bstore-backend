<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $brandId = (int) $this->route('id');
        $brandTable = (new Brand)->getConnectionName().'.brands';

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique($brandTable, 'name')->ignore($brandId)],
            'slug' => ['sometimes', 'nullable', 'string', 'max:191', Rule::unique($brandTable, 'slug')->ignore($brandId)],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'logo' => $this->hasFile('logo')
                ? ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048']
                : ['sometimes', 'nullable', 'url', 'max:500'],
            'logo_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'logo_file' => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ];
    }
}
