<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveTemplateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $name = Str::squish($this->input('name'));
            $this->merge(['name' => $name, 'name_key' => Str::lower($name)]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:60'], 'name_key' => ['required', 'string', 'max:60', Rule::unique('template_categories')->ignore($this->route('category'))]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.required' => 'Isi nama kategori.', 'name.max' => 'Nama kategori maksimal 60 karakter.', 'name_key.unique' => 'Nama kategori sudah digunakan.'];
    }
}
