<?php

namespace App\Http\Requests;

use App\EmailContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'subject'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim($this->input($key))]);
            }
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:180'],
            'category_id' => ['required', 'integer', 'exists:template_categories,id'],
            ...EmailContent::rules('delta'),
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $body = implode('', array_column($this->input('delta.ops', []), 'insert'));

            if (mb_strlen($body) > 20000 || ! str_contains($body, '{{tracking_link}}')) {
                $validator->errors()->add('body', 'Isi pesan maksimal 20.000 karakter dan wajib menyertakan {{tracking_link}}.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Isi nama template.',
            'name.max' => 'Nama template maksimal 100 karakter.',
            'subject.required' => 'Isi subjek email.',
            'subject.max' => 'Subjek maksimal 180 karakter.',
            'category_id.exists' => 'Kategori sudah tidak tersedia.',
            'category_id.required' => 'Pilih kategori template.',
            'category_id.integer' => 'Pilih kategori template yang tersedia.',
            'delta.required' => 'Isi pesan email.',
            'delta.array' => 'Format isi pesan tidak didukung.',
            'delta.ops.required' => 'Isi pesan email.',
            'delta.ops.array' => 'Format isi pesan tidak didukung.',
            'delta.ops.list' => 'Format isi pesan tidak didukung.',
            'delta.ops.max' => 'Format pesan terlalu rumit. Kurangi pemformatan teks.',
            'delta.ops.*.array' => 'Format isi pesan tidak didukung.',
            'delta.ops.*.insert.string' => 'Isi pesan hanya mendukung teks, tanpa gambar atau lampiran.',
            'delta.ops.*.insert.max' => 'Isi pesan maksimal 20.000 karakter.',
            'delta.ops.*.attributes.array' => 'Pemformatan teks tidak didukung.',
            'delta.ops.*.attributes.link.url' => 'Tautan hanya boleh menggunakan http atau https.',
            'delta.ops.*.attributes.link.max' => 'Tautan maksimal 1.000 karakter.',
        ];
    }
}
