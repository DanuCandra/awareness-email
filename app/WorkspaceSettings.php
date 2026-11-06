<?php

namespace App;

use App\Models\WorkspaceSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkspaceSettings
{
    /** @return array<string, mixed> */
    public function all(): array
    {
        $rows = WorkspaceSetting::query()->get()->keyBy('section');
        $result = ['version' => 1];

        foreach (config('awareness.defaults') as $section => $defaults) {
            $values = $rows->get($section)?->values ?? [];
            $result[$section] = array_replace($defaults, array_intersect_key($values, $defaults));
        }

        $smtp = $rows->get('smtp');
        $passwordConfigured = filled($smtp?->getRawOriginal('secret'));
        $result['smtp'] += [
            'passwordConfigured' => $passwordConfigured,
            'configured' => $passwordConfigured && filled($result['smtp']['host']) && filled($result['smtp']['username']) && filled($result['smtp']['fromEmail']),
            'revision' => $smtp?->revision,
            'connectionCheckedAt' => $smtp?->connection_checked_at?->toISOString(),
            'connectionSuccessful' => $smtp?->connection_successful,
        ];
        $result['updatedAt'] = $rows->max('updated_at')?->toISOString() ?? '';

        return $result;
    }

    /** @param array<string, mixed> $data */
    public function save(string $section, array $data): void
    {
        DB::transaction(function () use ($section, $data): void {
            $setting = WorkspaceSetting::firstOrCreate(['section' => $section], [
                'values' => config('awareness.defaults.'.$section), 'revision' => (string) Str::uuid(),
            ]);
            $setting = WorkspaceSetting::query()->lockForUpdate()->findOrFail($setting->id);
            $values = array_replace(config('awareness.defaults.'.$section), $setting->values, array_intersect_key($data, config('awareness.defaults.'.$section)));

            if ($section === 'smtp') {
                $password = $data['password'] ?? null;
                $hasNewPassword = is_string($password) && $password !== '';
                $credentialsChanged = $values['host'] !== $setting->values['host'] || $values['username'] !== $setting->values['username'];
                if (! $hasNewPassword && (! filled($setting->getRawOriginal('secret')) || $credentialsChanged)) {
                    throw ValidationException::withMessages(['password' => $credentialsChanged && filled($setting->getRawOriginal('secret'))
                        ? 'Isi ulang password SMTP saat mengganti host atau username.'
                        : 'Isi password SMTP untuk konfigurasi pertama.']);
                }
                if ($hasNewPassword) {
                    $setting->secret = $password;
                }
                $values['port'] = (int) $values['port'];
                $values['sendIntervalSeconds'] = (int) $values['sendIntervalSeconds'];
                $setting->connection_checked_at = null;
                $setting->connection_successful = null;
            } elseif ($section === 'reports') {
                $values['timezone'] = 'Asia/Jakarta';
                $values['pageLength'] = (int) $values['pageLength'];
            } elseif ($section === 'audit') {
                $values['retentionMonths'] = (int) $values['retentionMonths'];
            }

            $setting->values = $values;
            $setting->revision = (string) Str::uuid();
            $setting->save();
        });
    }

    public function smtpForTest(?string $revision): WorkspaceSetting
    {
        $setting = WorkspaceSetting::query()->where('section', 'smtp')->first();
        if (! $setting || ! filled($setting->getRawOriginal('secret'))) {
            throw ValidationException::withMessages(['smtp' => 'Simpan konfigurasi SMTP beserta password sebelum menjalankan pengujian.']);
        }
        if (! is_string($revision) || ! hash_equals($setting->revision, $revision)) {
            throw ValidationException::withMessages(['smtp' => 'Pengaturan SMTP sudah berubah. Muat ulang halaman sebelum menguji.']);
        }

        return $setting;
    }
}
