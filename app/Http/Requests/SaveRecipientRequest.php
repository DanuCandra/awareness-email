<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRecipientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique('recipients')->ignore($this->route('recipient'))]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['email.required' => 'Isi alamat email.', 'email.email' => 'Masukkan alamat email yang valid.', 'email.unique' => 'Email ini sudah ada di daftar.', 'email.max' => 'Email maksimal 254 karakter.'];
    }
}
