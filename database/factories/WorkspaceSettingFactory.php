<?php

namespace Database\Factories;

use App\Models\WorkspaceSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<WorkspaceSetting> */
class WorkspaceSettingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['section' => 'identity', 'values' => config('awareness.defaults.identity'), 'revision' => (string) Str::uuid()];
    }

    public function smtp(): static
    {
        return $this->state(fn (): array => [
            'section' => 'smtp',
            'values' => [
                'host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'starttls', 'username' => 'smtp-user',
                'fromEmail' => 'awareness@example.com', 'fromName' => 'Tim IT', 'replyTo' => 'helpdesk@example.com', 'sendIntervalSeconds' => 5,
            ],
            'secret' => 'SMTP-password-for-tests',
        ]);
    }
}
