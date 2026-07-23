<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoginFromNewDeviceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $ip,
        public readonly \DateTimeInterface $loggedInAt,
    ) {}

    public function build(): self
    {
        return $this->subject('New login to your NkwaLedger account')
            ->text('mail.login-from-new-device');
    }
}
