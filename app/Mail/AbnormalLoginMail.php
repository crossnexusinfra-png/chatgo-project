<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbnormalLoginMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $ip,
        public string $country,
        public string $time
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Abnormal login attempts detected',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.abnormal-login',
        );
    }
}
