<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SmtpTestMessage extends Mailable
{
    public function __construct(public string $companyName, public string $fromAddress, public string $senderName, public string $replyAddress = '') {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->senderName),
            replyTo: $this->replyAddress !== '' ? [new Address($this->replyAddress)] : [],
            subject: 'Email percobaan SMTP — Security Awareness',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.smtp-test');
    }
}
