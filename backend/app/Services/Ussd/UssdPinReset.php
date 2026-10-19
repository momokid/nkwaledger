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
    public function request(User $asker): string
    {
        // a farmer asks their agent; an agent asks the admins
        $isAgent = $asker->hasRole('agent');
        $profile = $asker->farmerProfile;
        $recipients = $isAgent
            ? User::role('admin')->where('is_active', true)->get()
            : collect([$profile?->assignedAgent])->filter();

        if ($recipients->isEmpty()) {
            return 'no_agent';
        }

        // one request an hour, so nobody can fill anyone's notices
        if (! Cache::add('ussd-pin-request:' . $asker->id, 1, now()->addHour())) {
            return 'already';
        }

        $message = $this->text->in('en')->get('pin_request_notice', ['name' => trim("{$asker->surname} {$asker->first_name}")]);
        $link = $isAgent ? '/admin/staff' : "/agent/farmers/{$profile->uuid}";

        $recipients->each(fn(User $to) => $this->notifications->send($to, 'ussd.pin_request', $message, $link));
        $this->audit->recordOn('ussd_pin.requested', $asker, null, null, $asker);

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
