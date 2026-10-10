<?php

// the Loan repayment and Non-cash lines show only when their figure is above 0, on My Records, Reports, the
// CSV and the print view, and enter the signed figures only when they are not zero. Time and the farmers'
// uuids are fixed, so every signed value and code below is a constant that can be checked by hand.

require_once __DIR__ . '/../../Support/LockedFiguresFixture.php';
require_once __DIR__ . '/../../Support/FigureSnapshot.php';

use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\Ledger\Reports\AccountStatementService;

beforeEach(function () {
    $this->travelTo('2026-10-10 12:00:00');
    buildLockedFigures($this);
    wireFiguresToAnAgent($this);
    addLoanTestTemplates($this);
    addNonCashTestTemplates($this);

    $this->profile->forceFill(['uuid' => 'aaaaaaaa-0000-4000-8000-000000000001'])->save();
    $this->clean->forceFill(['uuid' => 'aaaaaaaa-0000-4000-8000-000000000002'])->save();

    // a borrower who can log in: cash sale 1000, feed 400, loan received 500, loan repaid 200 and 80 (cancelled),
    // a goods loan of 700 and 60 (cancelled), feed in kind 150, produce in kind 90
    $this->borrowerUser = User::factory()->create();
    $this->borrowerUser->assignRole('farmer');
    $this->borrower = FarmerProfile::factory()->create(['user_id' => $this->borrowerUser->id, 'uuid' => 'aaaaaaaa-0000-4000-8000-000000000003']);
    $unit = ($this->unitOf)($this->borrower);
    $put = fn($template, $amount) => ($this->put)($this->borrower, $unit, $template, $amount);

    $put($this->saleTemplate, '1000');
    $put($this->feedTemplate, '400');
    $put($this->loanReceivedTemplate, '500');
    $put($this->loanRepaidTemplate, '200');
    $cancelledRepayment = $put($this->loanRepaidTemplate, '80');
    $put($this->goodsLoanTemplate, '700');
    $put($this->feedInKindTemplate, '150');
    $put($this->produceInKindTemplate, '90');
    $cancelledGoods = $put($this->goodsLoanTemplate, '60');
    ($this->cancel)($cancelledRepayment);
    ($this->cancel)($cancelledGoods);

    // a second borrower with a loan received and nothing else: no loan repayment, no non-cash
    $this->loanInOnly = FarmerProfile::factory()->create(['uuid' => 'aaaaaaaa-0000-4000-8000-000000000004']);
    ($this->put)($this->loanInOnly, ($this->unitOf)($this->loanInOnly), $this->loanReceivedTemplate, '500');

    $this->from = '2026-10-04';
    $this->to = '2026-10-10';
    $this->range = "from={$this->from}&to={$this->to}";

    $this->statement = fn($farmer) => app(AccountStatementService::class)->for($farmer->id, $this->from, $this->to);

    $this->expectedCode = function ($farmer, array $figures) {
        ksort($figures);
        $payload = implode('|', ['Account Statement', $farmer->uuid, $this->from, $this->to, 'confirmed', json_encode($figures)]);

        return strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', hash_hmac('sha256', $payload, config('app.report_secret'))), 0, 12));
    };

    $this->csvLines = fn($user) => collect(preg_split('/\r?\n/', trim(
        $this->actingAs($user)->get("/my-reports/csv?{$this->range}")->assertOk()->getContent(),
    )))->map(fn($line) => str_getcsv($line));
});

// --- signing ---

test('the clean farmer signs exactly the same nine values and gets the same code as before loans existed', function () {
    $s = ($this->statement)($this->clean);

    expect($s->header->verificationCode)->toBe('402D94C4287D')
        ->and($s->header->verificationCode)->toBe(($this->expectedCode)($this->clean, [
            'opening' => 0, 'in' => 100000, 'out' => 70000, 'closing' => 30000, 'page' => 1,
            'assets' => 30000, 'expenditure' => 40000, 'income' => 100000, 'liability' => 0,
        ]));
});

test('the main farmer, with cancelled records but no loan or non-cash record, keeps the same code too', function () {
    expect(($this->statement)($this->profile)->header->verificationCode)->toBe('440D0C357D46');
});

test('a farmer with a loan received but no repayment and no non-cash record signs the same nine keys', function () {
    $s = ($this->statement)($this->loanInOnly);

    expect($s->header->verificationCode)->toBe(($this->expectedCode)($this->loanInOnly, [
        'opening' => 0, 'in' => 50000, 'out' => 0, 'closing' => 50000, 'page' => 1,
        'assets' => 0, 'expenditure' => 0, 'income' => 0, 'liability' => 50000,
    ]));
});

test('loan repayment and non-cash enter the signed figures when they are not zero, equal to what is shown', function () {
    $s = ($this->statement)($this->borrower);

    expect([$s->totalLoanRepaymentMinor, $s->nonCashMinor])->toBe([28000, 94000])
        ->and($s->header->verificationCode)->toBe(($this->expectedCode)($this->borrower, [
            'opening' => 0, 'in' => 150000, 'out' => 68000, 'closing' => 90000, 'page' => 1,
            'assets' => 0, 'expenditure' => 40000, 'income' => 100000, 'liability' => 50000,
            'loan_repayment' => $s->totalLoanRepaymentMinor, 'non_cash' => $s->nonCashMinor,
        ]));
});

// --- My Records and Reports pages ---

test('my records sends the loan repayment and non-cash figures, and the non-cash rows with their own amount', function () {
    $statement = null;
    $this->actingAs($this->borrowerUser)->get("/my-records?{$this->range}&per_page=50")->assertOk()
        ->assertInertia(function ($page) use (&$statement) {
            $statement = $page->toArray()['props']['statement'];
        });

    $nonCash = collect($statement['rows'])->where('is_non_cash', true)->pluck('non_cash')->sort()->values()->all();

    expect([$statement['total_loan_repayment'], $statement['non_cash']])->toBe([28000, 94000])
        ->and($nonCash)->toBe([6000, 9000, 15000, 70000]);
});

test('the reports page sends the same two figures', function () {
    $report = null;
    $this->actingAs($this->borrowerUser)->get("/my-reports?kind=statement&{$this->range}")->assertOk()
        ->assertInertia(function ($page) use (&$report) {
            $report = $page->toArray()['props']['report'];
        });

    expect([$report['total_loan_repayment'], $report['non_cash']])->toBe([28000, 94000]);
});

// --- CSV ---

test('csv with loan and non-cash records: the extra column, the Loan repayment class and the two subtotal lines', function () {
    $lines = ($this->csvLines)($this->borrowerUser);

    $summary = $lines->filter(fn($line) => preg_match('/^(Totals|Of which|Non-cash)/', $line[2] ?? ''))->values()->all();

    expect($lines->first())->toBe(['Date', 'Reference', 'Description', 'Money In (GHS)', 'Money Out (GHS)', 'Balance (GHS)', 'Type', 'Non-cash'])
        ->and($summary)->toBe([
            ['', '', 'Totals', '1500.00', '680.00', '900.00', '', ''],
            ['', '', 'Of which: Income', '1000.00', '', '', '', ''],
            ['', '', 'Of which: Liability', '500.00', '', '', '', ''],
            ['', '', 'Of which: Assets', '', '0.00', '', '', ''],
            ['', '', 'Of which: Expenditure', '', '400.00', '', '', ''],
            ['', '', 'Of which: Loan repayment', '', '280.00', '', '', ''],
            ['', '', 'Non-cash', '', '', '', '', '940.00'],
        ])
        ->and($lines->skip(1)->pluck(6)->filter()->unique()->sort()->values()->all())->toBe(['Expenditure', 'Income', 'Liability', 'Loan repayment'])
        ->and($lines->pluck(7)->filter()->sort()->values()->all())->toBe(['60.00', '90.00', '150.00', '700.00', '940.00', 'Non-cash']);
});

test('csv without loan or non-cash records has no extra column and none of the new words', function () {
    foreach ([$this->farmerUser] as $user) {
        $csv = $this->actingAs($user)->get("/my-reports/csv?{$this->range}")->assertOk()->getContent();

        expect(collect(preg_split('/\r?\n/', trim($csv)))->map(fn($line) => count(str_getcsv($line)))->unique()->all())->toBe([7])
            ->and($csv)->not->toContain('Loan repayment')->not->toContain('Non-cash');
    }
});

// --- print view ---

test('print view with loan and non-cash records shows the new class, tag and subtotal lines', function () {
    $this->actingAs($this->borrowerUser)->get("/my-reports/print?kind=statement&{$this->range}")
        ->assertOk()
        ->assertSeeInOrder(['Totals', '1,500.00', '680.00', '900.00', 'Loan repayment', '280.00', 'Non-cash', '940.00'], false)
        ->assertSee('Non-cash', false);
});

test('print view without loan or non-cash records has none of the new words', function () {
    $html = $this->actingAs($this->farmerUser)->get("/my-reports/print?kind=statement&{$this->range}")->assertOk()->getContent();

    expect($html)->not->toContain('Loan repayment')->not->toContain('Non-cash');
});
