<?php

namespace App\Services;

use App\Models\ContactRequest;
use App\Models\ProduceListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

// a buyer's "I'm interested" against anything contactable - resolves who should be
// notified without ever printing a phone number on the listing itself
class ContactRequestService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function requestFor(Model $contactable, User $requester, ?string $message = null): ContactRequest
    {
        $recipient = $this->recipientFor($contactable);

        $request = ContactRequest::create([
            'contactable_type' => $contactable->getMorphClass(),
            'contactable_id' => $contactable->getKey(),
            'requester_user_id' => $requester->id,
            'recipient_user_id' => $recipient->id,
            'message' => $message,
        ]);

        $this->notifications->send(
            $recipient,
            'marketplace.contact_request',
            'Someone is interested in your produce listing.',
        );

        return $request;
    }

    // whoever posted the listing already knows about it and can respond - the
    // farmer for their own listing, or the agent when they posted it on the farmer's
    // behalf; the farmer themselves would not yet know an agent-posted draft exists
    private function recipientFor(Model $contactable): User
    {
        if ($contactable instanceof ProduceListing) {
            return $contactable->postedBy;
        }

        throw new \InvalidArgumentException('This kind of record cannot be contacted.');
    }
}
