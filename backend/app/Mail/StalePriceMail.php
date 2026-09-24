<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StalePriceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $bodyText) {}

    public function build(): self
    {
        return $this->subject('A price needs a quick check')
            ->text('mail.otp');
    }
}
