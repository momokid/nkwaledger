<?php

use App\Contracts\SmsProvider;
use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\Notification;
use App\Models\ProduceListing;
use App\Models\User;
use App\Services\ContactRequestService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->service = app(ContactRequestService::class);

    $this->recipient = User::factory()->create(['phone' => '0244000501']);
    $this->requester = User::factory()->create();
    $this->listing = ProduceListing::factory()->create(['posted_by_user_id' => $this->recipient->id]);
});

// Gap 1: no phone numbers anywhere until a reply
test('neither number is visible before a reply', function () {
    $request = $this->service->requestFor($this->listing, $this->requester, '0244445566', 'Interested');

    expect($request->status)->toBe(ContactRequestStatus::Sent)
        ->and($request->revealedSenderPhone())->toBeNull()
        ->and($request->revealedRecipientPhone())->toBeNull()
        // the raw column still holds it - the gate is the accessor, not the storage
        ->and($request->sender_phone)->toBe('0244445566');
});

// Gap 2: the state machine - Sent -> Replied, and only then do both numbers appear
test('both numbers become visible to both parties once the recipient replies', function () {
    $request = $this->service->requestFor($this->listing, $this->requester, '0244445566', 'Interested');

    $replied = $this->service->reply($request, $this->recipient, 'Yes, still have some.');

    expect($replied->status)->toBe(ContactRequestStatus::Replied)
        ->and($replied->replied_at)->not->toBeNull()
        ->and($replied->reply_message)->toBe('Yes, still have some.')
        ->and($replied->revealedSenderPhone())->toBe('0244445566')
        ->and($replied->revealedRecipientPhone())->toBe('0244000501');
});

// Gap 4 (recipient-only reply): the requester, or any third party, cannot reply
test('only the actual recipient can reply - not the requester, not a stranger', function () {
    $request = $this->service->requestFor($this->listing, $this->requester, '0244445566');
    $stranger = User::factory()->create();

    expect(fn() => $this->service->reply($request, $this->requester, 'Nice try'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn() => $this->service->reply($request->fresh(), $stranger, 'Nice try'))
        ->toThrow(InvalidArgumentException::class);

    expect($request->fresh()->status)->toBe(ContactRequestStatus::Sent)
        ->and($request->fresh()->revealedSenderPhone())->toBeNull()
        ->and($request->fresh()->revealedRecipientPhone())->toBeNull();
});

// Gap 3: expiry - an already-Expired request never reveals anything, even on a reply attempt
test('an expired request cannot be replied to and reveals nothing', function () {
    $request = ContactRequest::factory()->expired()->create([
        'contactable_id' => $this->listing->id,
        'requester_user_id' => $this->requester->id,
        'recipient_user_id' => $this->recipient->id,
        'sender_phone' => '0244445566',
    ]);

    expect(fn() => $this->service->reply($request, $this->recipient, 'Too late, sorry'))
        ->toThrow(InvalidArgumentException::class);

    expect($request->fresh()->status)->toBe(ContactRequestStatus::Expired)
        ->and($request->fresh()->replied_at)->toBeNull()
        ->and($request->fresh()->revealedSenderPhone())->toBeNull()
        ->and($request->fresh()->revealedRecipientPhone())->toBeNull();
});

// Gap 3, the sharper case: status still says "sent" because the scheduled sweep has
// not run yet, but the reply window has already passed - the guard must not trust
// the status column alone
test('a request whose expiry has passed but has not yet been swept still cannot be replied to', function () {
    $request = ContactRequest::factory()->create([
        'contactable_id' => $this->listing->id,
        'requester_user_id' => $this->requester->id,
        'recipient_user_id' => $this->recipient->id,
        'sender_phone' => '0244445566',
        'status' => ContactRequestStatus::Sent,
        'expires_at' => now()->subMinute(),
    ]);

    expect($request->canBeReplied())->toBeFalse();

    expect(fn() => $this->service->reply($request, $this->recipient, 'Late reply'))
        ->toThrow(InvalidArgumentException::class);

    expect($request->fresh()->revealedSenderPhone())->toBeNull()
        ->and($request->fresh()->revealedRecipientPhone())->toBeNull();
});

test('the scheduled sweep moves only overdue Sent requests to Expired', function () {
    $overdue = ContactRequest::factory()->create([
        'contactable_id' => $this->listing->id,
        'requester_user_id' => $this->requester->id,
        'recipient_user_id' => $this->recipient->id,
        'sender_phone' => '0244445566',
        'expires_at' => now()->subDay(),
    ]);
    $notYetDue = ContactRequest::factory()->create([
        'contactable_id' => $this->listing->id,
        'requester_user_id' => $this->requester->id,
        'recipient_user_id' => $this->recipient->id,
        'sender_phone' => '0244445566',
        'expires_at' => now()->addDay(),
    ]);
    $alreadyReplied = ContactRequest::factory()->replied()->create([
        'contactable_id' => $this->listing->id,
        'requester_user_id' => $this->requester->id,
        'recipient_user_id' => $this->recipient->id,
        'sender_phone' => '0244445566',
        'expires_at' => now()->subDay(),
    ]);

    $count = $this->service->expireOverdue();

    expect($count)->toBe(1)
        ->and($overdue->fresh()->status)->toBe(ContactRequestStatus::Expired)
        ->and($notYetDue->fresh()->status)->toBe(ContactRequestStatus::Sent)
        ->and($alreadyReplied->fresh()->status)->toBe(ContactRequestStatus::Replied);
});

// the SMS/notification gap - the recipient gets both, so they can call back
// even without opening the app
test('sending a contact request notifies the recipient in-app and by SMS', function () {
    $this->service->requestFor($this->listing, $this->requester, '0244445566', 'Interested');

    expect(Notification::where('user_id', $this->recipient->id)->where('kind', 'marketplace.contact_request')->exists())->toBeTrue()
        ->and(app(SmsProvider::class)->sentTo('0244000501'))->toBeTrue();
});

// unchanged behaviour: an agent-posted listing's agent is contacted, not the farmer,
// until the farmer takes it over - this reasoning was correct in the original stub
// and this rebuild must not disturb it
test('recipientFor still resolves to whoever posted the listing, agent or farmer', function () {
    $agent = User::factory()->create();
    $agentListing = ProduceListing::factory()->create(['posted_by_user_id' => $agent->id]);

    $request = $this->service->requestFor($agentListing, $this->requester, '0244445566');

    expect($request->recipient_user_id)->toBe($agent->id);
});
