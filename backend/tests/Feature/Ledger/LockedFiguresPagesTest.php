<?php

// Locks today's figures on the pages a person sees: My Records, Reports (page, CSV and print),
// the farmer dashboard, My Farm and the credit tab. Uses the shared fixture in tests/Support.

require_once __DIR__ . '/../../Support/LockedFiguresFixture.php';

beforeEach(function () {
    buildLockedFigures($this);

    $this->props = function ($user, string $url) {
        $props = null;

        $this->actingAs($user)->get($url)->assertOk()->assertInertia(function ($page) use (&$props) {
            $props = $page->toArray()['props'];
        });

        return $props;
    };

    $this->range = "from={$this->from}&to={$this->to}";
});

// --- My Records ---

test('my records: the statement totals, the cancelled amount and the row count', function () {
    $s = ($this->props)($this->farmerUser, "/my-records?{$this->range}&per_page=50")['statement'];

    expect([
        'in' => $s['total_in'],
        'out' => $s['total_out'],
        'closing' => $s['closing_balance'],
        'assets' => $s['total_assets'],
        'expenditure' => $s['total_expenditure'],
        'income' => $s['total_income'],
        'liability' => $s['total_liability'],
        'cancelled' => $s['cancelled'],
        'rows' => count($s['rows']),
    ])->toBe([
        'in' => 155000, 'out' => 85000, 'closing' => 50000,
        'assets' => 30000, 'expenditure' => 55000, 'income' => 155000, 'liability' => 0,
        'cancelled' => 50000, 'rows' => 12,
    ]);
});

test('my records: the cancel state and class tag of the cancelled sale and expense rows', function () {
    $rows = collect(($this->props)($this->farmerUser, "/my-records?{$this->range}&per_page=50")['statement']['rows']);

    $cancelled = $rows->where('cancel_state', 'cancelled')->map(fn($row) => [$row['money_in'], $row['money_out'], $row['money_class']])->values()->all();

    expect($cancelled)->toBe([[25000, 0, 'income'], [0, 15000, 'expenditure'], [10000, 0, 'income']]);
});

// --- credit tab ---

test('credit tab: one record, 500 on credit, 300 still owed, the cancelled payment not counted', function () {
    $rows = ($this->props)($this->farmerUser, "/my-records?{$this->range}")['creditRows'];

    expect(collect($rows)->map(fn($row) => [$row['amount'], $row['outstanding']])->all())->toBe([[50000, 30000]]);
});

// --- Reports page ---

test('reports page: the statement figures', function () {
    $report = ($this->props)($this->farmerUser, "/my-reports?kind=statement&{$this->range}")['report'];

    expect([
        'in' => $report['total_in'], 'out' => $report['total_out'], 'closing' => $report['closing_balance'],
        'assets' => $report['total_assets'], 'expenditure' => $report['total_expenditure'],
        'income' => $report['total_income'], 'liability' => $report['total_liability'],
        'cancelled' => $report['cancelled'], 'rows' => count($report['rows']),
    ])->toBe([
        'in' => 155000, 'out' => 85000, 'closing' => 50000,
        'assets' => 30000, 'expenditure' => 55000, 'income' => 155000, 'liability' => 0,
        'cancelled' => 50000, 'rows' => 12,
    ]);
});

test('reports page: the income and expenditure figures', function () {
    $report = ($this->props)($this->farmerUser, "/my-reports?kind=income&{$this->range}")['report'];

    expect([
        'income' => $report['total_income'], 'expense' => $report['total_expense'], 'loss' => $report['total_loss'],
        'net' => $report['net'], 'cash_collected' => $report['cash_collected'], 'cash_paid_out' => $report['cash_paid_out'],
    ])->toBe(['income' => 150000, 'expense' => 70000, 'loss' => 12000, 'net' => 68000, 'cash_collected' => 120000, 'cash_paid_out' => 70000]);
});

test('reports page for staff: the trial balance totals', function () {
    $report = ($this->props)($this->admin, "/admin/farmers/{$this->profile->uuid}/reports?kind=trial-balance&{$this->range}")['report'];

    expect([$report['total_debit'], $report['total_credit'], $report['is_balanced']])->toBe([352000, 352000, true]);
});

// --- CSV ---

test('csv: the totals line and the "of which" lines', function () {
    $csv = $this->actingAs($this->farmerUser)->get("/my-reports/csv?{$this->range}")->assertOk()->getContent();

    $lines = collect(preg_split('/\r?\n/', trim($csv)))->map(fn($line) => str_getcsv($line));

    $summary = $lines->filter(fn($line) => str_starts_with($line[2] ?? '', 'Totals') || str_starts_with($line[2] ?? '', 'Of which'))->values()->all();

    expect($summary)->toBe([
        ['', '', 'Totals', '1550.00', '850.00', '500.00', ''],
        ['', '', 'Of which: Income', '1550.00', '', '', ''],
        ['', '', 'Of which: Assets', '', '300.00', '', ''],
        ['', '', 'Of which: Expenditure', '', '550.00', '', ''],
    ]);
});

test('csv: every row line with its money and class tag', function () {
    $csv = $this->actingAs($this->farmerUser)->get("/my-reports/csv?{$this->range}")->assertOk()->getContent();

    $lines = collect(preg_split('/\r?\n/', trim($csv)))->map(fn($line) => str_getcsv($line));

    $rows = $lines->slice(2, 12)->map(fn($line) => [$line[3], $line[4], $line[5], $line[6]])->values()->all();

    expect($rows)->toBe([
        ['1000.00', '', '1000.00', 'Income'],
        ['', '400.00', '600.00', 'Expenditure'],
        ['', '300.00', '300.00', 'Asset'],
        ['', '', '300.00', ''],
        ['', '', '300.00', ''],
        ['200.00', '', '500.00', 'Income'],
        ['250.00', '', '750.00', 'Income'],
        ['', '150.00', '600.00', 'Expenditure'],
        ['100.00', '', '700.00', 'Income'],
        ['', '250.00', '450.00', 'Income'],
        ['150.00', '', '600.00', 'Expenditure'],
        // the correction of the cancelled payment now carries the payment's class (it used to carry none)
        ['', '100.00', '500.00', 'Income'],
    ]);
});

// --- print view ---

test('print view: the statement totals and the three class lines', function () {
    $this->actingAs($this->farmerUser)->get("/my-reports/print?kind=statement&{$this->range}")
        ->assertOk()
        ->assertSeeInOrder(['Totals', '1,550.00', '850.00', '500.00', 'Assets', '300.00', 'Expenditure', '550.00', 'Income', '1,550.00'], false);
});

test('print view: the income and expenditure sections', function () {
    $this->actingAs($this->farmerUser)->get("/my-reports/print?kind=income&{$this->range}")
        ->assertOk()
        ->assertSeeInOrder(['What came in', '1,500.00', 'What went out', '700.00', 'What was lost', '120.00'], false);
});

// --- farmer dashboard ---

test('farmer dashboard: income, expense, net, cash collected and cash paid out', function () {
    $summary = ($this->props)($this->farmerUser, '/farmer/dashboard')['summary'];

    // net here is income minus expense (the loss is shown on its own)
    expect([
        'income' => $summary['total_income'], 'expense' => $summary['total_expense'], 'net' => $summary['net'],
        'cash_collected' => $summary['cash_collected'], 'cash_paid_out' => $summary['cash_paid_out'],
    ])->toBe(['income' => 150000, 'expense' => 70000, 'net' => 80000, 'cash_collected' => 120000, 'cash_paid_out' => 70000]);
});

test('farmer dashboard: the loss line and the recent list without the cancelled records', function () {
    $props = ($this->props)($this->farmerUser, '/farmer/dashboard');

    expect([
        'loss' => collect($props['breakdown']['loss_rows'])->sum('amount'),
        'recent' => collect($props['recent_transactions'])->map(fn($row) => [$row['amount'], $row['income']])->all(),
    ])->toBe([
        'loss' => 12000,
        'recent' => [[50000, true], [30000, false], [40000, false], [100000, true]],
    ]);
});

// --- My Farm ---

test('my farm: income, expense, loss, net, sold and stock for the unit', function () {
    $analysis = ($this->props)($this->farmerUser, '/my-farm')['units'][0]['analysis'];

    expect([
        'income' => $analysis['total_income'], 'expense' => $analysis['total_expense'],
        'loss' => $analysis['total_loss'], 'net' => $analysis['net'],
        'sold' => $analysis['produce_quantity_sold'], 'stock' => $analysis['current_stock'],
    ])->toBe(['income' => 150000, 'expense' => 70000, 'loss' => 12000, 'net' => 80000, 'sold' => '0', 'stock' => '98']);
});

// --- admin region detail and approval queue ---

test('admin region detail: income 100.00 and expense 0 for the farmer with one live and one cancelled sale (the cancelled one used to add 250.00)', function () {
    $farmers = $this->actingAs($this->admin)
        ->get("/admin/regions/{$this->region->id}/detail?{$this->range}")
        ->assertOk()->json('farmers');

    expect(collect($farmers)->map(fn($farmer) => [$farmer['income'], $farmer['expense']])->all())->toBe([[10000, 0]]);
});

test('approval queue: the unapproved unit shows 1 provisional record (the cancelled sale and its correction used to make it 3)', function () {
    $props = ($this->props)($this->admin, '/admin/approvals');

    $item = collect($props['items']['data'])->first(fn($row) => isset($row['details']['provisional_records']));

    expect($item['details']['provisional_records'])->toBe(1);
});
