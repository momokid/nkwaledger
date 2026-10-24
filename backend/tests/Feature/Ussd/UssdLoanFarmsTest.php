<?php

// Task 5b of credit-score-and-loan-use: an agent's new money loan on USSD names the farms, an amount for each, and what each is for.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\PendingEntry;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    buildLoanWorld($this);
    config(['services.ussd.secret' => 'test-secret']);

    $this->agent->forceFill(['phone' => '0201112233', 'phone_verified_at' => now(), 'ussd_pin_hash' => Hash::make('4829'), 'first_name' => 'Ama'])->save();
    $this->farmerUser->forceFill(['phone' => '0244445566', 'phone_verified_at' => now(), 'first_name' => 'Kofi'])->save();

    // three farms, in the order the list shows them
    $this->unit->update(['name' => 'Zeta']);
    $this->alpha = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $this->profile->community_id, 'name' => 'Alpha']);
    $this->beta = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $this->profile->community_id, 'name' => 'Beta']);

    // eight spending records (the fixture has Animal purchase and Feed expense), so the list runs to a second page
    foreach (range(1, 6) as $n) {
        $copy = $this->feedTemplate->replicate();
        $copy->fill(['name' => "Expense {$n}", 'slug' => "expense-{$n}"])->save();
    }

    $this->seen = [];
    $this->dial = function (string $input = '', bool $new = false) {
        $reply = $this->postJson('/ussd/callback/test-secret', ['sessionID' => 'a-1', 'msisdn' => '233201112233', 'newSession' => $new, 'userData' => $input]);
        $this->seen[] = $reply->json('message');

        return $reply;
    };
    // a new money loan for the farmer, up to the first farm screen
    $this->toFarms = function (string $phone = '0244445566') {
        foreach (['', '4829', '1', $phone, '6', '1', 'sika', '1', '500', '1', '1', '1'] as $i => $input) {
            $reply = ($this->dial)($input, $i === 0);
        }

        return $reply;
    };
    $this->entry = fn() => PendingEntry::where('farmer_profile_id', $this->profile->id)->firstOrFail();
});

test('the whole walk: several farms, a purpose for each, and the last farm takes the rest', function () {
    $farms = ($this->toFarms)()->json('message');
    expect($farms)->toContain('Which farm will use this loan?')->toContain('1. Alpha')->toContain('2. Beta')->toContain('3. Zeta');

    expect(($this->dial)('1')->json('message'))->toContain('Amount for this farm')->toContain('Left to place: GHS 500.00');

    $purposes = ($this->dial)('300')->json('message');
    expect($purposes)->toContain('What will this money be used for?')->toContain('1. Animal purchase')->toContain('5. Expense 4')->toContain('6. More')->not->toContain('Something else');

    expect(($this->dial)('1')->json('message'))->toContain('Left to place: GHS 200.00')->toContain('1. Another farm')->toContain('2. Put the rest on this farm');

    $next = ($this->dial)('1')->json('message');
    expect($next)->toContain('1. Beta')->toContain('2. Zeta')->not->toContain('Alpha');

    expect(($this->dial)('1')->json('message'))->toContain('Left to place: GHS 200.00');
    expect(($this->dial)('100')->json('message'))->toContain('6. More');

    $second = ($this->dial)('6')->json('message');
    expect($second)->toContain('1. Expense 5')->toContain('2. Expense 6')->toContain('3. Feed expense')->toContain('4. Something else')->not->toContain('More');

    expect(($this->dial)('4')->json('message'))->toContain('What will you use it for?');
    expect(($this->dial)('Fencing')->json('message'))->toContain('Left to place: GHS 100.00');

    expect(($this->dial)('2')->json('message'))->toContain('Sika Microfinance')->toContain('GHS 500.00')->toContain('1. Yes');
    ($this->dial)('1');

    expect(($this->entry)()->payload['farms'])->toBe([
        ['farm_unit' => $this->alpha->uuid, 'amount_minor' => 30000, 'purpose' => 'animal-purchase', 'purpose_note' => null],
        ['farm_unit' => $this->beta->uuid, 'amount_minor' => 20000, 'purpose' => 'other', 'purpose_note' => 'Fencing'],
    ]);
});

test('when only one farm is left it takes the rest without being asked an amount', function () {
    ($this->toFarms)();
    ($this->dial)('1');
    ($this->dial)('300');
    ($this->dial)('1');
    ($this->dial)('1');
    ($this->dial)('1');
    ($this->dial)('100');
    ($this->dial)('1');

    expect(($this->dial)('1')->json('message'))->toContain('1. Zeta')->and(true)->toBeTrue();

    $purposes = ($this->dial)('1')->json('message');
    expect($purposes)->toContain('What will this money be used for?')->not->toContain('Amount for this farm');

    ($this->dial)('1');
    ($this->dial)('1');

    expect(array_column(($this->entry)()->payload['farms'], 'amount_minor'))->toBe([30000, 10000, 10000]);
});

test('an amount above what is left, or not an amount, asks again', function () {
    ($this->toFarms)();
    ($this->dial)('1');

    expect(($this->dial)('500.01')->json('message'))->toContain('That is more than what is left to place.');
    expect(($this->dial)('0.00')->json('message'))->toContain('Enter an amount above 0');
    expect(($this->dial)('abc')->json('message'))->toContain('Enter an amount above 0');
    expect(($this->dial)('500')->json('message'))->toContain('What will this money be used for?');
});

test('an empty note asks again', function () {
    ($this->toFarms)();
    ($this->dial)('1');
    ($this->dial)('500');
    ($this->dial)('6');
    ($this->dial)('4');

    expect(($this->dial)('   ')->json('message'))->toContain('Please say what you will use it for.');
    expect(PendingEntry::count())->toBe(0);
});

test('no screen is longer than a USSD reply may be', function () {
    ($this->toFarms)();
    ($this->dial)('1');
    ($this->dial)('300');
    ($this->dial)('6');
    ($this->dial)('4');

    foreach ($this->seen as $screen) {
        expect(mb_strlen($screen))->toBeLessThanOrEqual(182);
    }
});

test('Back steps one screen back through the farm steps', function () {
    ($this->toFarms)();
    expect(($this->dial)('0')->json('message'))->toContain('Due date?');

    ($this->dial)('1');
    ($this->dial)('1');
    expect(($this->dial)('0')->json('message'))->toContain('Which farm will use this loan?');

    ($this->dial)('1');
    ($this->dial)('300');
    expect(($this->dial)('0')->json('message'))->toContain('Amount for this farm');
});

test('a farmer with no farm cannot be given a money loan, and an existing loan needs none', function () {
    $user = User::factory()->create(['phone' => '0277778888', 'phone_verified_at' => now()]);
    $user->assignRole('farmer');
    FarmerProfile::factory()->create(['user_id' => $user->id, 'assigned_agent_id' => $this->agent->id]);

    // up to choosing "New loan": the session ends there
    foreach (['', '4829', '1', '0277778888', '6'] as $i => $input) {
        ($this->dial)($input, $i === 0);
    }

    $end = ($this->dial)('1');
    expect($end->json('continueSession'))->toBeFalse()->and($end->json('message'))->toContain('This farmer has no farm yet, so a loan cannot be recorded.');
    expect(PendingEntry::count())->toBe(0);
});

test('every new line has a Twi draft', function () {
    $en = json_decode(file_get_contents(resource_path('ussd.en.json')), true);
    $tw = json_decode(file_get_contents(resource_path('ussd.tw.json')), true);

    foreach (['loan_farm_pick', 'loan_farm_amount', 'loan_farm_too_much', 'loan_purpose_pick', 'more', 'loan_purpose_other', 'loan_purpose_note', 'loan_purpose_note_empty', 'loan_farm_more', 'loan_no_farm'] as $key) {
        expect($en)->toHaveKey($key);
        expect($tw)->toHaveKey($key);
    }
});

test('the farmer accepting a loan entered on USSD gets the farm amounts and purposes on the loan', function () {
    ($this->toFarms)();

    // Alpha 300 for the first record type, another farm, Beta 200, then confirm
    foreach (['1', '300', '1', '1', '1', '200', '1', '1'] as $input) {
        ($this->dial)($input);
    }

    $entry = ($this->entry)();

    $this->actingAs($this->farmerUser)->post("/loans/entries/{$entry->uuid}/accept")->assertRedirect('/loans');

    $loan = \App\Models\Loan::firstOrFail();

    expect($loan->farmAllocations()->orderBy('amount_minor')->pluck('amount_minor')->all())->toBe([20000, 30000]);
    expect($loan->farmAllocations()->whereNotNull('purpose_template_id')->count())->toBe(2);
    expect($loan->unaccountedMinor())->toBe(50000);
});
