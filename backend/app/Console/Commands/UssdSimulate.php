<?php

namespace App\Console\Commands;

use App\Services\Ussd\UssdFlow;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

// plays a USSD session in the terminal against the same flow the gateway calls. Never on a live server
class UssdSimulate extends Command
{
    protected $signature = 'ussd:simulate {phone : the farmer\'s number, as they would dial it}';

    protected $description = 'Play a USSD session in the terminal (local and testing only)';

    public function handle(UssdFlow $flow): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('The USSD simulator runs only on local and testing.');

            return self::FAILURE;
        }

        $session = 'sim-' . Str::random(8);
        $input = '';
        $new = true;

        while (true) {
            $reply = $flow->handle($session, (string) $this->argument('phone'), $new, $input);
            $this->line($reply['message']);

            if (! $reply['continue']) {
                return self::SUCCESS;
            }

            $input = (string) $this->ask('Reply');
            $new = false;
        }
    }
}
