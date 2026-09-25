<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class KioskReportedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $bodyText) {}

    public function build(): self
    {
        return $this->subject('A farmer has reported your kiosk')
            ->text('mail.otp');
    }
}
