<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ExpireVerificationCommand extends Command
{
    protected $signature = 'verification:expire';

    protected $description = 'Unverify users whose verification window has passed';

    public function handle(): int
    {
        $expired = 0;

        User::query()
            ->whereNotNull('phone_verified_at')
            ->whereNotNull('next_verification_at')
            ->where('next_verification_at', '<=', now())
            ->chunkById(500, function ($users) use (&$expired) {
                foreach ($users as $user) {
                    // admins prove their phone every login already
                    if ($user->hasRole('admin')) {
                        continue;
                    }

                    $user->forceFill(['phone_verified_at' => null])->save();

                    $expired++;
                }
            });

        $this->info("Expired {$expired} verification(s).");

        return self::SUCCESS;
    }
}
