<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginOtp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your Reels login code'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.login-otp',
            text: 'emails.auth.login-otp-text',
        );
    }
}
