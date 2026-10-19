<?php

// USSD for agents: an agent signs in with their own PIN, picks one of their own farmers by phone number, and works on that
// farmer's behalf: summary, loans, income, expenditure, farm sales and a temporary PIN.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Contracts\SmsProvider;
use App\Models\AuditLog;
use App\Models\FarmType;
use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\Transaction;
use App\Models\Notification;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use App\Support\Money;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    buildLoanWorld($this);
    config(['services.ussd.secret' => 'test-secret']);

    $this->agent->forceFill(['phone' => '0201112233', 'phone_verified_at' => now(), 'ussd_pin_hash' => Hash::make('4829'), 'first_name' => 'Ama'])->save();
    $this->farmerUser->forceFill(['phone' => '0244445566', 'phone_verified_at' => now(), 'first_name' => 'Kofi'])->save();
    $this->profile->farmTypes()->attach(FarmType::factory()->create()->id);

    $sales = LedgerAccount::where('name', 'Sales A/C')->firstOrFail();
    $feed = LedgerAccount::where('name', 'Feed A/C')->firstOrFail();
    $make = fn(array $over) => TransactionTemplate::create($over + ['requires_farm_unit' => false, 'settlement_side' => 'debit']);
    $make(['name' => 'I paid for transport', 'slug' => 'transport_cost', 'transaction_type' => 'EXPENSE', 'debit_account_id' => $feed->id, 'credit_account_id' => $this->cash->id, 'settlement_side' => 'credit']);
    $make(['name' => 'I sold my farm produce', 'slug' => 'produce_sale', 'transaction_type' => 'INCOME', 'requires_farm_unit' => true, 'is_produce_sale' => true, 'allows_credit' => true, 'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id]);

    $this->dial = fn(string $input = '', bool $new = false) => $this->postJson('/ussd/callback/test-secret', [
        'sessionID' => 'a-1', 'msisdn' => '233201112233', 'newSession' => $new, 'userData' => $input,
    ]);
    $this->walk = function (string ...$inputs) {
        ($this->dial)('', true);
        $reply = ($this->dial)('4829');

        foreach ($inputs as $input) {
            $reply = ($this->dial)($input);
        }

        return $reply;
    };
    $this->ussd = fn() => Transaction::where('channel', 'ussd')->get();
});

test('an agent sets a PIN on the first dial and sees the agent menu, not the farmer menu', function () {
    $this->agent->forceFill(['ussd_pin_hash' => null])->save();

    ($this->dial)('', true);
    ($this->dial)('4829');
    $menu = ($this->dial)('4829');

    expect($menu->json('message'))->toContain('Hello Ama.')->toContain('1. Work on a farmer')->toContain('5. Choose Language')->toContain('6. Exit')->toContain('7. Change PIN')
        ->not->toContain('Log income');
});

test('the agent asks for a farmer by phone number and gets that farmer\'s menu', function () {
    $ask = ($this->walk)('1');
    expect($ask->json('message'))->toContain('farmer')->toContain('0. Back');

    $menu = ($this->dial)('0244445566');

    expect($menu->json('message'))->toContain('Kofi')->toContain('1. Summary')->toContain('2. Loans')->toContain('3. Log income')->toContain('4. Log expenditure')
        ->toContain('5. Set farmer PIN')->toContain('0. Back');
});

test('a number that is not the agent\'s farmer gets one answer, however it fails', function () {
    $theirs = FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);
    $theirs->user->forceFill(['phone' => '0551112233', 'phone_verified_at' => now()])->save();
    $theirs->user->assignRole('farmer');

    ($this->walk)('1');

    $answers = collect(['0551112233', '0279998877', '0201112233', 'abc'])->map(fn($number) => ($this->dial)($number)->json('message'));

    expect($answers->unique())->toHaveCount(1)->and($answers->first())->toContain('farmer');
});

test('the agent reads the farmer\'s summary and loans', function () {
    ($this->openLoan)();
    ($this->walk)('1', '0244445566');

    $report = app(IncomeAndExpenditureService::class)->for($this->profile->id, today()->startOfMonth()->toDateString(), today()->toDateString(), includeProvisional: true);

    ($this->dial)('1');
    $summary = ($this->dial)('2');
    expect($summary->json('message'))->toContain('Income: ' . Money::format($report->totalIncomeMinor));

    ($this->dial)('', true);
    ($this->dial)('4829');
    ($this->dial)('1');
    ($this->dial)('0244445566');
    expect(($this->dial)('2')->json('message'))->toContain('Total owed: GHS 1,200.00');
});

test('an expenditure logged by the agent is the farmer\'s record, recorded by the agent, and the farmer is told by SMS', function () {
    ($this->walk)('1', '0244445566', '4', '1', '50', '2', '1');

    $transaction = ($this->ussd)()->firstOrFail();
    $sms = collect(app(SmsProvider::class)->sent)->last();

    expect([$transaction->farmer_profile_id, $transaction->recorded_by, $transaction->amount_minor])->toBe([$this->profile->id, $this->agent->id, 5000])
        ->and($sms['phone'])->toBe('0244445566')
        ->and($sms['message'])->toContain($transaction->reference)->toContain('GHS 50.00');
});

test('a farm sale logged by the agent uses the farmer\'s own units', function () {
    ($this->walk)('1', '0244445566', '3', '1', '1', '10', '200', '1', '1');

    $transaction = ($this->ussd)()->firstOrFail();

    expect([$transaction->farm_unit_id, $transaction->recorded_by])->toBe([$this->unit->id, $this->agent->id]);
});

test('a farmer who cannot record yet is explained to the agent and nothing is saved', function () {
    $this->profile->forceFill(['is_active' => false])->save();

    $reply = ($this->walk)('1', '0244445566', '4');

    expect($reply->json('message'))->toContain('on hold')->and(($this->ussd)())->toHaveCount(0);
});

test('the agent sets a temporary PIN for the farmer after confirming, and it is shown once', function () {
    ($this->walk)('1', '0244445566', '5');

    $ask = ($this->dial)('');
    expect($ask->json('message'))->toContain('checked');

    $no = ($this->dial)('2');
    expect($this->farmerUser->fresh()->ussd_pin_must_change)->toBeFalse();

    ($this->walk)('1', '0244445566', '5');
    $done = ($this->dial)('1');
    $pin = preg_match('/Temporary PIN: (\d{4})/', $done->json('message'), $match) ? $match[1] : null;

    expect($done->json('continueSession'))->toBeFalse()->and($pin)->not->toBeNull()
        ->and($this->farmerUser->fresh()->ussd_pin_must_change)->toBeTrue()
        ->and(Hash::check($pin, $this->farmerUser->fresh()->ussd_pin_hash))->toBeTrue()
        ->and(AuditLog::where('action', 'ussd_pin.reset')->count())->toBe(1);
});

test('Back from a farmer screen returns to that farmer\'s menu, and Back from the menu returns to the agent menu', function () {
    ($this->walk)('1', '0244445566', '1');

    expect(($this->dial)('0')->json('message'))->toContain('Kofi')->toContain('1. Summary')
        ->and(($this->dial)('0')->json('message'))->toContain('1. Work on a farmer');
});

test('a farmer cannot use the agent menu', function () {
    $this->farmerUser->forceFill(['ussd_pin_hash' => Hash::make('4829')])->save();
    $farmer = fn(string $input, bool $new = false) => $this->postJson('/ussd/callback/test-secret', ['sessionID' => 'f-1', 'msisdn' => '233244445566', 'newSession' => $new, 'userData' => $input]);

    $farmer('', true);
    $menu = $farmer('4829');

    expect($menu->json('message'))->not->toContain('Work on a farmer');
});

test('an agent who forgot their PIN asks the admins, once an hour', function () {
    ($this->dial)('', true);
    $sent = ($this->dial)('0');

    $notices = Notification::where('kind', 'ussd.pin_request')->where('user_id', $this->admin->id)->get();

    expect($sent->json('continueSession'))->toBeFalse()->and($sent->json('message'))->toContain('admin')
        ->and($notices)->toHaveCount(1)->and($notices->first()->message)->toContain('Ama');

    ($this->dial)('', true);
    expect(($this->dial)('0')->json('message'))->toContain('already')
        ->and(Notification::where('kind', 'ussd.pin_request')->where('user_id', $this->admin->id)->count())->toBe(1);
});

test('an admin sets an agent\'s temporary PIN, and the agent must replace it', function () {
    $response = $this->actingAs($this->admin)->postJson("/admin/staff/{$this->agent->uuid}/ussd-pin")->assertOk();
    $pin = $response->json('pin');
    $agent = $this->agent->fresh();

    expect($pin)->toMatch('/^\d{4}$/')->and([$agent->ussd_pin_must_change, Hash::check($pin, $agent->ussd_pin_hash)])->toBe([true, true]);

    ($this->dial)('', true);
    expect(($this->dial)($pin)->json('message'))->toContain('new PIN');
});

test('only an admin can set a staff member\'s temporary PIN, and only for an agent', function () {
    $this->actingAs($this->agent)->postJson("/admin/staff/{$this->agent->uuid}/ussd-pin")->assertForbidden();
    $this->actingAs($this->admin)->postJson("/admin/staff/{$this->vet->uuid}/ussd-pin")->assertNotFound();
});
