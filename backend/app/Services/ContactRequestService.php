<?php

namespace App\Services;

use App\Contracts\SmsProvider;
use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\ProduceListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

// a buyer's "I'm interested" against anything contactable - resolves who should be
// notified without ever printing a phone number on the listing itself. Neither side's
// number is visible anywhere until the recipient actually replies; see
// ContactRequest::revealedSenderPhone()/revealedRecipientPhone() for the gate itself
class ContactRequestService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly SmsProvider $sms,
        private readonly SettingsService $settings,
        private readonly AuditService $audit,
    ) {}

    public function requestFor(Model $contactable, User $requester, string $senderPhone, ?string $message = null): ContactRequest
    {
        $recipient = $this->recipientFor($contactable);
        $days = $this->settings->getInt('marketplace.contact_request_expiry_days');

        $request = ContactRequest::create([
            'contactable_type' => $contactable->getMorphClass(),
            'contactable_id' => $contactable->getKey(),
            'requester_user_id' => $requester->id,
            'recipient_user_id' => $recipient->id,
            'message' => $message,
            'sender_phone' => $senderPhone,
            'expires_at' => now()->addDays($days),
        ]);

        $this->notifications->send(
            $recipient,
            'marketplace.contact_request',
            'Someone is interested in your produce listing.',
        );

        // notified both ways, so a recipient without the app open can still call back -
        // the whole point of Step 6, which the earlier stub never actually did
        if ($recipient->phone !== null) {
            $this->sms->send(
                $recipient->phone,
                'NkwaLedger: someone is interested in your produce listing. Open the app to reply and see their number.',
            );
        }

        $this->audit->recordOn('contact_request.sent', $request);

        return $request->fresh();
    }

    // the farmer's or their agent's tap - the only one this request can ever be
    // replied to by. Never trusts status alone: canBeReplied() also checks expires_at
    // live, so a request that expired but has not yet been swept by the scheduled
    // command still cannot be replied to and reveal anything
    public function reply(ContactRequest $contactRequest, User $actor, string $replyMessage): ContactRequest
    {
        if ($contactRequest->recipient_user_id !== $actor->id) {
            throw new InvalidArgumentException('Only the person this request was sent to can reply.');
        }

        if (! $contactRequest->canBeReplied()) {
            throw new InvalidArgumentException('This request is no longer open to a reply.');
        }

        $contactRequest->update([
            'status' => ContactRequestStatus::Replied,
            'replied_at' => now(),
            'reply_message' => $replyMessage,
        ]);

        $this->notifications->send(
            $contactRequest->requester,
            'marketplace.contact_request_replied',
            'Your contact request was replied to.',
        );

        $this->audit->recordOn('contact_request.replied', $contactRequest);

        return $contactRequest->fresh();
    }

    // Sent requests past their own expiry never got a reply - move them to Expired so
    // nothing can be revealed on them again, matching Step 3's escalation command shape
    public function expireOverdue(): int
    {
        $overdue = ContactRequest::query()
            ->where('status', ContactRequestStatus::Sent)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($overdue as $contactRequest) {
            $contactRequest->update(['status' => ContactRequestStatus::Expired]);
            $this->audit->recordOn('contact_request.expired', $contactRequest);
        }

        return $overdue->count();
    }

    // whoever posted the listing already knows about it and can respond - the
    // farmer for their own listing, or the agent when they posted it on the farmer's
    // behalf; the farmer themselves would not yet know an agent-posted draft exists
    private function recipientFor(Model $contactable): User
    {
        if ($contactable instanceof ProduceListing) {
            return $contactable->postedBy;
        }

        throw new InvalidArgumentException('This kind of record cannot be contacted.');
    }
}
