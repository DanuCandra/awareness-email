<?php

namespace App\Http\Requests;

use App\EmailContent;
use App\Models\Recipient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'description', 'senderName', 'scheduledAt', 'endsAt'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim($this->input($key))]);
            }
        }
        if (is_string($this->input('message.subject'))) {
            $this->merge(['message' => [...$this->input('message'), 'subject' => trim($this->input('message.subject'))]]);
        }
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        $scheduled = $this->input('status') === 'scheduled';

        return [
            'clientToken' => [$this->isMethod('post') ? 'required' : 'sometimes', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['draft', 'scheduled'])],
            'senderName' => [$scheduled ? 'required' : 'nullable', 'string', 'max:80', 'not_regex:/[\r\n]/'],
            'scheduledAt' => [$scheduled ? 'required' : 'nullable', 'date_format:Y-m-d\TH:i'],
            'endsAt' => [$scheduled ? 'required' : 'nullable', 'date_format:Y-m-d\TH:i', 'after:scheduledAt'],
            'recipientMode' => ['required', Rule::in(['all', 'selected'])],
            'recipientIds' => ['sometimes', 'array', 'max:10000'],
            'recipientIds.*' => ['integer', 'distinct', Rule::exists('recipients', 'id')],
            'templateId' => ['nullable', 'integer', Rule::exists('email_templates', 'id')],
            'message' => ['required', 'array:subject,delta'],
            'message.subject' => [$scheduled ? 'required' : 'nullable', 'string', 'max:180', 'not_regex:/[\r\n]/'],
            ...EmailContent::rules('message.delta', $scheduled),
        ];
    }

    /** @return array<int,\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->input('status') === 'scheduled') {
                $start = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $this->input('scheduledAt'), 'Asia/Jakarta');
                if ($start->lte(now())) {
                    $validator->errors()->add('scheduledAt', 'Pilih jadwal mendatang dalam zona waktu WIB.');
                }
                $body = EmailContent::text($this->input('message.delta', []));
                if (mb_strlen($body) > 20000 || ! str_contains($body, '{{tracking_link}}')) {
                    $validator->errors()->add('body', 'Isi pesan maksimal 20.000 karakter dan wajib menyertakan tautan simulasi.');
                }
                if ($this->input('recipientMode') === 'selected' && $this->input('recipientIds', []) === [] || $this->input('recipientMode') === 'all' && ! Recipient::query()->exists()) {
                    $validator->errors()->add('recipients', 'Pilih minimal satu email penerima.');
                }
            } elseif (mb_strlen(EmailContent::text($this->input('message.delta', []))) > 20000) {
                $validator->errors()->add('body', 'Isi pesan maksimal 20.000 karakter.');
            }
        }];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['required' => ':attribute wajib diisi.', 'max.string' => ':attribute maksimal :max karakter.', 'scheduledAt.date_format' => 'Isi tanggal dan jam mulai yang valid.', 'endsAt.date_format' => 'Isi tanggal dan jam selesai yang valid.', 'endsAt.after' => 'Waktu selesai harus setelah waktu mulai.', 'recipientIds.*.exists' => 'Daftar penerima berubah. Muat ulang dan pilih email yang tersedia.', 'message.subject.not_regex' => 'Subjek tidak boleh berisi baris baru.', 'senderName.not_regex' => 'Nama pengirim tidak boleh berisi baris baru.'];
    }
}
