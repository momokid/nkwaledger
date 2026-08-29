<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OtpService;
use App\Support\Phone;
use Illuminate\Console\Command;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create
        {surname : The person\'s surname}
        {first_name : The person\'s first name}
        {phone : Their phone number}
        {--email= : Their email address}';

    protected $description = 'Create an admin and send them a code to set their password';

    public function handle(OtpService $otp): int
    {
        $phone = Phone::normalise($this->argument('phone')) ?? $this->argument('phone');

        if (User::where('phone', $phone)->exists()) {
            $this->error('That phone number already has an account.');

            return self::FAILURE;
        }

        // no password is set here, so nothing secret is typed into a terminal
        $user = User::create([
            'surname' => $this->argument('surname'),
            'first_name' => $this->argument('first_name'),
            'phone' => $phone,
            'email' => $this->option('email'),
            'password' => null,
        ]);

        $user->assignRole('admin');

        $otp->generate($phone, 'invitation');

        $this->info("Admin created. A code has been sent to {$phone}.");
        $this->line('They should open /activate to set their password.');

        return self::SUCCESS;
    }
}
