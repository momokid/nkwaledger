<?php

// The approved repayment wording reaches the farmer and the agent: the SMS, the notifications, and the flash messages.
// No raw key such as repayment.date may show anywhere.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\Notification;
use App\Support\Money;
use App\Support\Wording;

const RAW_KEY = '/\b(repayment|lender)\.[a-z_]+/';

beforeEach(function () {
    buildLoanWorld($this);
    $this->loan = ($this->openLoan)();
    $this->pendingRepay = fn(string $amount) => $this->entries->recordRepayment($this->loan, $this->agent, ($this->repayForm)($amount, []));
    $this->agentName = trim("{$this->farmerUser->surname} {$this->farmerUser->first_name}");
    $this->latest = fn($user) => Notification::where('user_id', $user->id)->latest('id')->value('message');
});

test('the repayment SMS is the approved sentence with the amount, lender and code filled in', function () {
    $entry = ($this->pendingRepay)('600');
    $this->codes->send($entry, $this->agent);

    $sent = end(app(\App\Contracts\SmsProvider::class)->sent)['message'];
    preg_match('/Code: (\d{6})$/', $sent, $match);
    $code = $match[1] ?? '';

    expect($sent)->toBe(Wording::get('repayment.sms', ['amount' => Money::toDecimal(60000), 'lender' => $entry->lenderName(), 'code' => $code]))
        ->and($sent)->toStartWith('Your agent recorded a repayment of GHS 600.00 to ')
        ->and($sent)->not->toMatch(RAW_KEY);
});

test('the farmer is told in the approved words that a repayment waits for them', function () {
    ($this->pendingRepay)('600');

    expect(($this->latest)($this->farmerUser))->toBe('Your agent recorded a repayment for you. It is not counted until you accept it.');
});

test('the agent is told the outcome with the farmer name, a colon, then the approved sentence', function () {
    $accepted = ($this->pendingRepay)('600');
    $this->entries->accept($accepted, $this->farmerUser, 'app');
    expect(($this->latest)($this->agent))->toBe("{$this->agentName}: Repayment accepted.");

    $declined = ($this->pendingRepay)('100');
    $this->entries->decline($declined, $this->farmerUser);
    expect(($this->latest)($this->agent))->toBe("{$this->agentName}: The farmer declined the repayment.");

    $stale = ($this->pendingRepay)('100');
    ($this->repay)($this->loan, '100');
    $this->entries->accept($stale, $this->farmerUser, 'app');
    expect(($this->latest)($this->agent))->toBe("{$this->agentName}: Repayment rejected");
});

test('a cancelled loan tells the farmer in the approved words that the waiting repayment was rejected', function () {
    ($this->pendingRepay)('600');
    $this->entries->rejectRepaymentsFor($this->loan, $this->agent);

    expect(($this->latest)($this->farmerUser))->toBe('Repayment rejected');
});

test('the flash messages after accepting are the approved words', function () {
    $entry = ($this->pendingRepay)('600');

    $this->actingAs($this->farmerUser)->post("/loans/entries/{$entry->uuid}/accept")->assertSessionHas('success', 'Repayment accepted.');

    $stale = ($this->pendingRepay)('100');
    ($this->repay)($this->loan, '100');

    $this->actingAs($this->farmerUser)->post("/loans/entries/{$stale->uuid}/accept")
        ->assertSessionHas('error', 'The loan changed, so this repayment was not accepted.');
});

test('no notification or SMS shows a raw key', function () {
    $entry = ($this->pendingRepay)('600');
    $this->codes->send($entry, $this->agent);
    $this->entries->accept($entry, $this->farmerUser, 'app');
    $this->entries->decline(($this->pendingRepay)('100'), $this->farmerUser);

    $messages = Notification::pluck('message')->merge(collect(app(\App\Contracts\SmsProvider::class)->sent)->pluck('message'));

    expect($messages->count())->toBeGreaterThan(2)
        ->and($messages->filter(fn($message) => preg_match(RAW_KEY, $message))->all())->toBe([]);
});

// a name too long for one SMS is cut in the SMS only; the amount and the code stay whole, and the code stays last
test('a very long lender name is cut to fit 160 characters in the SMS and stays whole everywhere else', function () {
    $name = mb_substr(str_repeat('Asante Akim North Rural Bank ', 9), 0, 255);
    $loan = ($this->openLoan)(['lender_name' => $name]);
    $entry = $this->entries->recordRepayment($loan, $this->agent, ($this->repayForm)('600', []));
    $this->codes->send($entry, $this->agent);

    $sent = end(app(\App\Contracts\SmsProvider::class)->sent)['message'];

    expect(mb_strlen($sent))->toBeLessThanOrEqual(160)
        ->and($sent)->toStartWith('Your agent recorded a repayment of GHS 600.00 to Asante Akim North Rural Bank')
        ->and($sent)->toMatch('/\.\.\.\. Code: \d{6}$/')
        ->and($entry->lenderName())->toBe($name);
});

test('a name that fits is sent as it is, accents and curly quotes included', function () {
    $name = 'Société d’Épargne';
    $loan = ($this->openLoan)(['lender_name' => $name]);
    $entry = $this->entries->recordRepayment($loan, $this->agent, ($this->repayForm)('600', []));
    $this->codes->send($entry, $this->agent);

    expect(end(app(\App\Contracts\SmsProvider::class)->sent)['message'])->toContain("to {$name}. Code: ");
});

test('in production a missing key inside the accept does not undo the accept', function () {
    $entry = ($this->pendingRepay)('600');
    $map = json_decode(file_get_contents(resource_path('wording.json')), true);
    unset($map['repayment.accepted']);
    $this->app['env'] = 'production';
    Wording::swap($map);

    try {
        $repayment = $this->entries->accept($entry, $this->farmerUser, 'app');
    } finally {
        Wording::swap(null);
    }

    expect($repayment)->toBeInstanceOf(\App\Models\LoanRepayment::class)
        ->and($entry->fresh()->status)->toBe('accepted')
        ->and($this->loan->fresh()->balanceMinor())->toBe(50000)
        ->and(($this->latest)($this->agent))->toBe("{$this->agentName}: Something went wrong. Please try again.");
});
