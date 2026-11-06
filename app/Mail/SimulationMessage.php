<?php

namespace App\Mail;

use App\EmailContent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SimulationMessage extends Mailable
{
    /** @param array{ops?:array} $delta */
    public function __construct(public string $subjectText, public array $delta, public string $recipientEmail, public string $simulationName, public string $trackingLink, public string $fromAddress, public string $senderName, public string $replyAddress = '') {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->senderName),
            replyTo: $this->replyAddress !== '' ? [new Address($this->replyAddress)] : [],
            subject: strtr($this->subjectText, ['{{email}}' => $this->recipientEmail, '{{campaign_name}}' => $this->simulationName]),
        );
    }

    public function content(): Content
    {
        $context = ['email' => $this->recipientEmail, 'campaignName' => $this->simulationName, 'link' => $this->trackingLink];

        return new Content(view: 'mail.simulation', text: 'mail.simulation-text', with: [
            'body' => app(EmailContent::class)->html($this->delta, $context),
            'plainBody' => strtr(EmailContent::text($this->delta), ['{{email}}' => $this->recipientEmail, '{{campaign_name}}' => $this->simulationName, '{{tracking_link}}' => $this->trackingLink]),
        ]);
    }
}
