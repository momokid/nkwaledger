<?php

// the statement reports what was cancelled as two amounts, measured on the cancelled records themselves:
// cancelled money in and cancelled money out. The old single "cancelled" figure is gone everywhere.

require_once __DIR__ . '/../../Support/LockedFiguresFixture.php';

use App\Services\Ledger\Reports\AccountStatementService;

beforeEach(function () {
    buildLockedFigures($this);

    $this->statement = fn($farmer = null) => app(AccountStatementService::class)
        ->for(($farmer ?? $this->profile)->id, $this->from, $this->to);

    $this->props = function ($user, string $url) {
        $props = null;

        $this->actingAs($user)->get($url)->assertOk()->assertInertia(function ($page) use (&$props) {
            $props = $page->toArray()['props'];
        });

        return $props;
    };
});

test('cancelled money in is the 250 sale and the 100 payment; cancelled money out is the 150 expense', function () {
    $s = ($this->statement)();

    expect([$s->cancelledInMinor, $s->cancelledOutMinor])->toBe([35000, 15000]);
});

test('closing balance = opening + money in - money out - cancelled money in + cancelled money out, with cancellations', function () {
    $s = ($this->statement)();

    expect($s->closingBalanceMinor)->toBe(
        $s->openingBalanceMinor + $s->totalInMinor - $s->totalOutMinor - $s->cancelledInMinor + $s->cancelledOutMinor,
    )->and($s->closingBalanceMinor)->toBe(50000);
});

test('the same holds for the farmer with nothing cancelled', function () {
    $s = ($this->statement)($this->clean);

    expect([$s->cancelledInMinor, $s->cancelledOutMinor])->toBe([0, 0])
        ->and($s->closingBalanceMinor)->toBe($s->openingBalanceMinor + $s->totalInMinor - $s->totalOutMinor)
        ->and($s->closingBalanceMinor)->toBe(30000);
});

test('a cancelled credit sale that moved no cash adds nothing to either amount', function () {
    $credit = ($this->put)($this->profile, $this->unit, $this->saleTemplate, '300', $this->receivable->id);
    ($this->cancel)($credit);

    $s = ($this->statement)();

    expect([$s->cancelledInMinor, $s->cancelledOutMinor, $s->closingBalanceMinor])->toBe([35000, 15000, 50000]);
});

test('my records sends the two cancelled amounts and no single cancelled figure', function () {
    $statement = ($this->props)($this->farmerUser, "/my-records?from={$this->from}&to={$this->to}&per_page=50")['statement'];

    expect([$statement['cancelled_in'], $statement['cancelled_out'], array_key_exists('cancelled', $statement)])
        ->toBe([35000, 15000, false]);
});

test('the reports page sends the two cancelled amounts and no single cancelled figure', function () {
    $report = ($this->props)($this->farmerUser, "/my-reports?kind=statement&from={$this->from}&to={$this->to}")['report'];

    expect([$report['cancelled_in'], $report['cancelled_out'], array_key_exists('cancelled', $report)])
        ->toBe([35000, 15000, false]);
});
