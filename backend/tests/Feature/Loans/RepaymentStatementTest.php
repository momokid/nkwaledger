<?php

// The repayment statement lists repayments oldest first by repayment date, and each line carries what was still owed
// after it: the amount granted plus the interest agreed, less every repayment up to and including that line. A
// cancelled repayment keeps its line but takes nothing off.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\LoanRepayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    buildLoanWorld($this);
    $this->loan = ($this->openLoan)(['date' => '2026-09-01']);
    $this->rows = fn() => $this->actingAs($this->farmerUser)->get("/loans/{$this->loan->uuid}")->viewData('page')['props']['repayments'];
    $this->cancel = function (LoanRepayment $repayment) {
        foreach (['principalTransaction', 'interestTransaction'] as $link) {
            $original = DB::table('transactions')->find($repayment->{$link}?->id);
            if (! $original) {
                continue;
            }
            $copy = (array) $original;
            unset($copy['id']);
            DB::table('transactions')->insert(array_merge($copy, ['uuid' => (string) Str::uuid(), 'idempotency_key' => null, 'reference' => random_int(100000000000, 999999999999),'transaction_type' => 'ADJUSTMENT', 'reverses_transaction_id' => $original->id]));
        }
    };
});

test('repayments are listed oldest first by repayment date, whatever order they were saved in', function () {
    ($this->repay)($this->loan, '600', ['date' => '2026-09-05']);
    ($this->repay)($this->loan, '200', ['date' => '2026-09-03']);

    expect(collect(($this->rows)())->pluck('date')->all())->toBe(['2026-09-03', '2026-09-05']);
});

test('two repayments on the same day keep the order they were saved in', function () {
    ($this->repay)($this->loan, '100', ['date' => '2026-09-03']);
    ($this->repay)($this->loan, '300', ['date' => '2026-09-03']);

    expect(collect(($this->rows)())->pluck('amount')->all())->toBe([10000, 30000]);
});

test('each line carries what was still owed after it, starting from the amount granted plus the interest agreed', function () {
    ($this->repay)($this->loan, '600', ['date' => '2026-09-05']);
    ($this->repay)($this->loan, '200', ['date' => '2026-09-03']);

    expect(collect(($this->rows)())->pluck('balance')->all())->toBe([100000, 40000]);
});

test('the last line agrees with the balance of the loan', function () {
    ($this->repay)($this->loan, '600', ['date' => '2026-09-05']);
    ($this->repay)($this->loan, '200', ['date' => '2026-09-03']);

    $props = $this->actingAs($this->farmerUser)->get("/loans/{$this->loan->uuid}")->viewData('page')['props'];

    expect(collect($props['repayments'])->last()['balance'])->toBe($props['loan']['balance']);
});

test('a cancelled repayment keeps its line but takes nothing off', function () {
    ($this->repay)($this->loan, '200', ['date' => '2026-09-03']);
    ($this->repay)($this->loan, '600', ['date' => '2026-09-05']);
    ($this->cancel)(LoanRepayment::where('amount_minor', 20000)->first());

    $rows = collect(($this->rows)());

    expect($rows->pluck('cancelled')->all())->toBe([true, false])
        ->and($rows->pluck('balance')->all())->toBe([120000, 60000]);
});

test('a loan with no interest agreed starts from the amount granted', function () {
    $loan = app(\App\Services\Loans\LoanService::class)->record($this->profile, $this->farmerUser, ($this->moneyEntry)(['amount' => '300']));
    ($this->repay)($loan, '100', ['date' => now()->toDateString()]);

    $rows = $this->actingAs($this->farmerUser)->get("/loans/{$loan->uuid}")->viewData('page')['props']['repayments'];

    expect($rows[0]['balance'])->toBe(20000);
});

test('the agent and the admin see the same sorted lines and balances', function () {
    ($this->repay)($this->loan, '600', ['date' => '2026-09-05']);
    ($this->repay)($this->loan, '200', ['date' => '2026-09-03']);

    $agent = $this->actingAs($this->agent)->get("/agent/loans/{$this->loan->uuid}")->viewData('page')['props']['repayments'];
    $admin = $this->actingAs($this->admin)->get("/admin/loans/{$this->loan->uuid}")->viewData('page')['props']['repayments'];

    expect(collect($agent)->pluck('balance')->all())->toBe([100000, 40000])
        ->and(collect($admin)->pluck('date')->all())->toBe(['2026-09-03', '2026-09-05']);
});

test('a repayment saved in place answers with the sorted lines and balances', function () {
    ($this->repay)($this->loan, '200', ['date' => '2026-09-03']);

    $body = $this->actingAs($this->farmerUser)
        ->postJson("/loans/{$this->loan->uuid}/repayments", ($this->repayForm)('600', ['paid_from' => 'cash', 'date' => '2026-09-05']))
        ->assertStatus(201)->json();

    expect(collect($body['repayments'])->pluck('balance')->all())->toBe([100000, 40000]);
});
