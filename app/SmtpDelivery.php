<?php

namespace App;

use App\Mail\SmtpTestMessage;
use App\Models\WorkspaceSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class SmtpDelivery
{
    public const MAILER = 'awareness-smtp';

    /** @return array<string, mixed> */
    public function configuration(WorkspaceSetting $setting): array
    {
        $smtp = $setting->values;

        return [
            'transport' => 'smtp',
            'scheme' => $smtp['encryption'] === 'tls' ? 'smtps' : 'smtp',
            'host' => $smtp['host'],
            'port' => (int) $smtp['port'],
            'username' => $smtp['username'],
            'password' => $setting->secret,
            'timeout' => config('awareness.smtp_timeout'),
            'auto_tls' => true,
            'require_tls' => true,
            'verify_peer' => true,
            'local_domain' => config('mail.mailers.smtp.local_domain'),
        ];
    }

    public function testConnection(WorkspaceSetting $setting): void
    {
        $transport = app(MailManager::class)->createSymfonyTransport($this->configuration($setting));
        if (! $transport instanceof EsmtpTransport) {
            throw new \LogicException('The awareness mailer requires SMTP.');
        }
        try {
            $transport->start();
        } finally {
            try {
                $transport->stop();
            } catch (TransportExceptionInterface) {
            }
        }
    }

    public function sendTest(WorkspaceSetting $setting, string $recipient, string $companyName): void
    {
        $smtp = $setting->values;
        $this->sendMessage($setting, $recipient, new SmtpTestMessage($companyName, $smtp['fromEmail'], $smtp['fromName'], $smtp['replyTo'] ?? ''));
    }

    public function sendMessage(WorkspaceSetting $setting, string $recipient, Mailable $message): void
    {
        $configKey = 'mail.mailers.'.self::MAILER;
        $previous = config($configKey);
        config([$configKey => $this->configuration($setting)]);
        Mail::purge(self::MAILER);
        try {
            Mail::mailer(self::MAILER)->to($recipient)->send($message);
        } finally {
            Mail::purge(self::MAILER);
            if ($previous === null) {
                config()->offsetUnset($configKey);
            } else {
                config([$configKey => $previous]);
            }
        }
    }
}
