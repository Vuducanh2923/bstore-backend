<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique((new Brand)->getConnectionName().'.brands', 'name')],
            'slug' => ['nullable', 'string', 'max:191', Rule::unique((new Brand)->getConnectionName().'.brands', 'slug')],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'logo' => $this->hasFile('logo')
                ? ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048']
                : ['nullable', 'url', 'max:500'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'logo_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ];
    }
}
