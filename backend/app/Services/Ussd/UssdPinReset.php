<?php

namespace App\Services\Ussd;

use App\Contracts\SmsProvider;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;

// a farmer who forgot the PIN asks their agent; the agent, once sure who is asking, sets a temporary PIN the farmer must replace
class UssdPinReset
{
    public function __construct(
        private readonly UssdPinService $pins,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
        private readonly SmsProvider $sms,
        private readonly UssdText $text,
    ) {}

    /** @return 'sent'|'already'|'no_agent' */
    public function request(User $farmer): string
    {
        $profile = $farmer->farmerProfile;
        $agent = $profile->assignedAgent;

        if ($agent === null) {
            return 'no_agent';
        }

        // one request an hour, so nobody can fill an agent's notices
        if (! Cache::add('ussd-pin-request:' . $farmer->id, 1, now()->addHour())) {
            return 'already';
        }

        $name = trim("{$farmer->surname} {$farmer->first_name}");
        $this->notifications->send($agent, 'ussd.pin_request', $this->text->in('en')->get('pin_request_notice', ['name' => $name]), "/agent/farmers/{$profile->uuid}");
        $this->audit->recordOn('ussd_pin.requested', $farmer, null, null, $farmer);

        return 'sent';
    }

    // the new PIN is returned to the agent once; the audit row and the SMS never carry it
    public function reset(User $farmer, User $agent): string
    {
        do {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (! $this->pins->isAllowed($pin));

        $this->pins->setTemporary($farmer, $pin);
        $this->audit->recordOn('ussd_pin.reset', $farmer, null, null, $agent);
        $this->sms->send($farmer->phone, $this->text->in($farmer->ussd_language ?? 'en')->get('pin_reset_sms'));

        return $pin;
    }
}
