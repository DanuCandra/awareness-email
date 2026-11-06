<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['username', 'email'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => Str::lower(trim($this->input($field)))]);
            }
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $admin = $this->route('admin');

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($admin)],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique('users', 'email')->ignore($admin)],
            'password' => [$admin ? 'nullable' : 'required', 'string', 'min:8', 'max:128', 'confirmed'],
            'password_confirmation' => ['nullable', 'string', 'required_with:password', 'same:password', 'max:128'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Isi nama admin.',
            'name.max' => 'Nama admin maksimal 100 karakter.',
            'username.required' => 'Isi username admin.',
            'username.min' => 'Username minimal 3 karakter.',
            'username.max' => 'Username maksimal 30 karakter.',
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.',
            'username.unique' => 'Username sudah digunakan oleh admin lain.',
            'email.required' => 'Isi email admin.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'email.unique' => 'Email sudah digunakan oleh admin lain.',
            'password.required' => 'Isi password untuk admin baru.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.max' => 'Password maksimal 128 karakter.',
            'password.confirmed' => 'Konfirmasi password belum sama.',
            'password_confirmation.same' => 'Konfirmasi password belum sama.',
            'password_confirmation.required_with' => 'Isi konfirmasi password.',
        ];
    }
}
