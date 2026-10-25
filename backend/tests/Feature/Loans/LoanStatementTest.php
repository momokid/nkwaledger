<?php

// The loan statement: one loan's details, repayments and what the money was used for (seed, animals, labour...),
// as a screen, a print page and a PDF, for the farmer, the farmer's agent and the admin.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\FarmUnit;
use App\Models\Transaction;

beforeEach(function () {
    buildLoanWorld($this);

    $this->alpha = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $this->profile->community_id, 'name' => 'Alpha']);
    $this->beta = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $this->profile->community_id, 'name' => 'Beta']);

    // a spending record that asks for no farm, like paying for work
    $this->labour = $this->feedTemplate->replicate();
    $this->labour->fill(['name' => 'I paid someone to work', 'slug' => 'labour-test', 'requires_farm_unit' => false])->save();

    // 1000 over two farms, 200 interest, a due date, and 200 paid back
    $this->loan = ($this->openLoan)(['amount' => '1000', 'interest' => '200', 'due_date' => now()->addMonth()->toDateString(), 'farms' => [
        ['farm_unit' => $this->alpha->uuid, 'amount' => '600', 'purpose' => $this->feedTemplate->slug],
        ['farm_unit' => $this->beta->uuid, 'amount' => '400', 'purpose' => 'other', 'purpose_note' => 'Fencing'],
    ]]);
    ($this->repay)($this->loan, '200');

    $this->spend = fn($template, string $amount, FarmUnit $unit, array $over = []) => $this->actingAs($this->farmerUser)->post('/my-records', array_merge([
        'transaction_template_id' => $template->id, 'amount' => $amount, 'settlement_account_id' => $this->cash->id,
        'transaction_date' => now()->toDateString(), 'farm_unit_id' => $unit->id, 'loan' => $this->loan->uuid,
    ], $over))->assertSessionHasNoErrors();

    ($this->spend)($this->feedTemplate, '120', $this->alpha);
    ($this->spend)($this->purchaseTemplate, '300', $this->alpha, ['quantity_purchased' => '5']);
    ($this->spend)($this->labour, '80', $this->beta);
    $this->actingAs($this->farmerUser)->post("/loans/{$this->loan->uuid}/other-uses", ['amount' => '50', 'note' => 'School fees'])->assertSessionHasNoErrors();

    $this->url = "/loans/{$this->loan->uuid}/statement";
    $this->props = fn($user = null) => $this->actingAs($user ?? $this->farmerUser)->get($this->url)->assertOk()->viewData('page')['props'];
});

test('the statement carries the loan, the repayments and what the money was used for', function () {
    $s = ($this->props)()['statement'];

    expect($s['loan'])->toMatchArray(['lender' => 'Sika Microfinance', 'amount' => 100000, 'interest' => 20000, 'balance' => 100000, 'status' => 'active', 'paid_into' => 'Cash']);
    expect(array_column($s['repayments'], 'amount'))->toBe([20000]);

    expect(array_map(fn($farm) => [$farm['name'], $farm['purpose'], $farm['planned'], $farm['accounted']], $s['farms']))
        ->toBe([['Alpha', $this->feedTemplate->name, 60000, 42000], ['Beta', 'Fencing', 40000, 8000]]);

    expect(array_map(fn($row) => [$row['what'], $row['farm'], $row['amount']], $s['purchases']))
        ->toBe([[$this->feedTemplate->name, 'Alpha', 12000], [$this->purchaseTemplate->name, 'Alpha', 30000], ['I paid someone to work', 'Beta', 8000]]);

    expect(array_column($s['kinds'], 'amount', 'name'))->toBe([$this->feedTemplate->name => 12000, $this->purchaseTemplate->name => 30000, 'I paid someone to work' => 8000]);
    expect(array_map(fn($row) => [$row['note'], $row['amount']], $s['other_uses']))->toBe([['School fees', 5000]]);
    expect($s['totals'])->toBe(['on_farms' => 50000, 'other' => 5000, 'not_yet' => 45000]);
    expect($s['prepared_on'])->toBe(now()->toDateString());
    expect($s['notice'])->toBe('You entered this loan yourself. It has not been checked with the lender.');
});

test('a cancelled purchase is not in the statement', function () {
    $bought = Transaction::where('loan_id', $this->loan->id)->where('amount_minor', 12000)->firstOrFail();
    ($this->cancel)($bought);

    $s = ($this->props)()['statement'];

    expect(array_column($s['purchases'], 'amount'))->toBe([30000, 8000]);
    expect($s['totals']['on_farms'])->toBe(38000);
});

test('the statement holds no numeric id', function () {
    $json = json_encode(($this->props)()['statement']);

    expect($json)->not->toContain('"id"')->not->toContain('farm_unit_id')->not->toContain('farmer_profile_id');
});

test('who may open the statement', function (string $who, int $status) {
    $users = ['farmer' => $this->farmerUser, 'otherFarmer' => $this->otherFarmerUser, 'agent' => $this->agent, 'otherAgent' => $this->otherAgent, 'admin' => $this->admin, 'vet' => $this->vet, 'adviser' => $this->adviser];

    foreach ([$this->url, "{$this->url}/print", "{$this->url}/pdf"] as $url) {
        $this->actingAs($users[$who])->get($url)->assertStatus($status);
    }
})->with([
    'the farmer' => ['farmer', 200],
    'another farmer' => ['otherFarmer', 404],
    'the farmer\'s agent' => ['agent', 200],
    'another agent' => ['otherAgent', 404],
    'an admin' => ['admin', 200],
    'a vet' => ['vet', 404],
    'an adviser' => ['adviser', 404],
]);

test('a guest is sent to sign in', function () {
    \Illuminate\Support\Facades\Auth::forgetGuards();
    $this->get($this->url)->assertRedirect('/login');
});

test('the print page uses the approved words', function () {
    $page = $this->actingAs($this->farmerUser)->get("{$this->url}/print")->assertOk();

    foreach ([
        'Loan statement', 'The loan', 'Repayments', 'What the money was used for',
        'Lender', 'Amount received', 'Date received', 'Paid into', 'Total interest agreed', 'Due date', 'Status', 'Loan balance',
        'Alpha: ' . $this->feedTemplate->name, 'Beta: Fencing',
        'Alpha: planned GHS 600.00, accounted GHS 420.00, not yet accounted GHS 180.00',
        'Date', 'What it was', 'Amount', 'I paid someone to work', $this->purchaseTemplate->name,
        'School fees: GHS 50.00', 'Used on the farms', 'Used for something else', 'Not yet accounted for',
        'Prepared on ' . now()->format('d/m/Y'), 'You entered this loan yourself. It has not been checked with the lender.',
    ] as $text) {
        $page->assertSee($text, false);
    }
});

test('the pdf downloads', function () {
    $response = $this->actingAs($this->farmerUser)->get("{$this->url}/pdf")->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment; filename="loan-statement-');
    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-');
});

test('an existing loan has a statement with no use of money', function () {
    $existing = ($this->pendingExisting)();
    $loan = app(\App\Services\Loans\LoanService::class)->record($this->profile, $this->farmerUser, ($this->existingEntry)());

    $s = $this->actingAs($this->farmerUser)->get("/loans/{$loan->uuid}/statement")->assertOk()->viewData('page')['props']['statement'];

    expect([$s['farms'], $s['purchases'], $s['other_uses'], $s['totals']['not_yet']])->toBe([[], [], [], 0]);
});
