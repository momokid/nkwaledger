<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $bodyText) {}

    public function build(): self
    {
        return $this->subject('Your NkwaLedger code')
            ->text('mail.otp');
    }
}
