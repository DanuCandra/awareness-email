<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveWorkspaceSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('section') === 'smtp' && is_string($this->input('host'))) {
            $this->merge(['host' => Str::lower(trim($this->input('host')))]);
        }
        foreach (['contactEmail', 'fromEmail', 'replyTo'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => Str::lower(trim($this->input($key)))]);
            }
        }
        foreach (['contactEmail', 'logo', 'replyTo', 'tips'] as $key) {
            if ($this->exists($key) && $this->input($key) === null) {
                $this->merge([$key => '']);
            }
        }
        if ($this->route('section') === 'landing' && is_string($this->input('baseUrl'))) {
            $this->merge(['baseUrl' => rtrim($this->input('baseUrl'), '/')]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return match ($this->route('section')) {
            'identity' => [
                'companyName' => ['required', 'string', 'max:100'],
                'contactEmail' => ['nullable', 'string', 'email:rfc', 'max:254'],
                'logo' => ['nullable', 'string', 'max:700000'],
            ],
            'smtp' => [
                'host' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/i'],
                'port' => ['required', 'integer', 'between:1,65535'],
                'encryption' => ['required', Rule::in(['starttls', 'tls'])],
                'username' => ['required', 'string', 'max:254', 'not_regex:/[\r\n]/'],
                'password' => ['nullable', 'string', 'max:200'],
                'fromEmail' => ['required', 'string', 'email:rfc', 'max:254'],
                'fromName' => ['required', 'string', 'max:80', 'not_regex:/[\r\n]/'],
                'replyTo' => ['nullable', 'string', 'email:rfc', 'max:254'],
                'sendIntervalSeconds' => ['required', 'integer', 'min:0', 'max:2147483647'],
            ],
            'landing' => [
                'baseUrl' => ['required', 'string', 'url:http,https', 'max:250'],
                'type' => ['required', Rule::in(['education', 'confirmation'])],
                'title' => ['required', 'string', 'max:120'],
                'message' => ['required', 'string', 'max:1200'],
                'tips' => [Rule::requiredIf($this->input('type') === 'education'), 'nullable', 'string', 'max:1500'],
            ],
            'reports' => [
                'pageLength' => ['required', 'integer', Rule::in([10, 25, 50])],
                'period' => ['required', Rule::in(['all', '30', '90'])],
            ],
            'audit' => ['retentionMonths' => ['required', 'integer', Rule::in([3, 6, 12])]],
            default => [],
        };
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->route('section') === 'smtp') {
                $labels = explode('.', $this->input('host'));
                foreach ($labels as $label) {
                    if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $label)) {
                        $validator->errors()->add('host', 'Isi hostname SMTP yang valid tanpa http:// atau https://.');
                        break;
                    }
                }
            }
            if ($this->route('section') === 'landing') {
                $parts = parse_url($this->input('baseUrl'));
                if ($parts === false || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
                    $validator->errors()->add('baseUrl', 'Gunakan URL tanpa kredensial, query, atau fragmen.');
                }
            }
            if ($this->route('section') === 'identity' && filled($this->input('logo'))) {
                $value = $this->input('logo');
                if (! preg_match('/^data:image\/(png|jpeg|webp);base64,([A-Za-z0-9+\/=]+)$/D', $value, $matches)) {
                    $validator->errors()->add('logo', 'Gunakan gambar PNG, JPG, atau WebP yang valid.');

                    return;
                }
                $bytes = base64_decode($matches[2], true);
                $image = $bytes === false ? false : @getimagesizefromstring($bytes);
                if ($bytes === false || strlen($bytes) > 500 * 1024 || $image === false || ($image['mime'] ?? '') !== 'image/'.$matches[1] || $image[0] > 4096 || $image[1] > 4096) {
                    $validator->errors()->add('logo', 'Gunakan gambar valid maksimal 500 KB dan 4.096 × 4.096 piksel.');
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'max.string' => ':attribute maksimal :max karakter.',
            'email' => 'Masukkan alamat email yang valid.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'in' => 'Pilih nilai :attribute yang tersedia.',
            'host.regex' => 'Isi hostname SMTP tanpa http:// atau https://.',
            'port.between' => 'Port harus antara 1–65535.',
            'sendIntervalSeconds.min' => 'Jeda minimal 0 detik; 0 berarti tanpa jeda.',
            'sendIntervalSeconds.max' => 'Jeda terlalu besar. Maksimal 2.147.483.647 detik.',
            'baseUrl.url' => 'Gunakan alamat dasar http atau https yang valid.',
            'username.not_regex' => 'Username SMTP tidak boleh mengandung baris baru.',
            'fromName.not_regex' => 'Nama pengirim tidak boleh mengandung baris baru.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'companyName' => 'Nama perusahaan', 'contactEmail' => 'Email kontak IT', 'logo' => 'Logo',
            'host' => 'Host SMTP', 'port' => 'Port', 'encryption' => 'Enkripsi', 'username' => 'Username SMTP',
            'password' => 'Password SMTP', 'fromName' => 'Nama pengirim', 'fromEmail' => 'Email pengirim',
            'replyTo' => 'Alamat balasan', 'sendIntervalSeconds' => 'Jeda antar email', 'baseUrl' => 'Alamat dasar tautan',
            'type' => 'Jenis halaman', 'title' => 'Judul halaman', 'message' => 'Pesan', 'tips' => 'Tips keamanan',
            'pageLength' => 'Jumlah baris', 'period' => 'Periode laporan', 'retentionMonths' => 'Masa penyimpanan',
        ];
    }
}
