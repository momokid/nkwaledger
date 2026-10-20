<?php

// A loan past its due date shows Overdue, and Default after 90 days, on the farmer's list and the admin's list.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\Loan;

beforeEach(function () {
    buildLoanWorld($this);

    $this->late = function (int $daysPastDue) {
        $loan = app(\App\Services\Loans\LoanService::class)->record($this->profile, $this->farmerUser, ($this->moneyEntry)([
            'date' => now()->subDays($daysPastDue + 30)->toDateString(),
            'due_date' => now()->subDays($daysPastDue)->toDateString(),
        ]));

        return $loan->uuid;
    };

    $this->farmerStatus = fn() => $this->actingAs($this->farmerUser)->get('/loans')->viewData('page')['props']['loans'][0]['status'];
    $this->adminStatus = fn() => $this->actingAs($this->admin)->getJson('/admin/loans')->json('rows.0.status');
});

test('the farmer and the admin see Overdue the day after the due date', function () {
    ($this->late)(1);

    expect(($this->farmerStatus)())->toBe('overdue')
        ->and(($this->adminStatus)())->toBe('overdue');
});

test('they see Default after 90 days', function () {
    ($this->late)(91);

    expect(($this->farmerStatus)())->toBe('default')
        ->and(($this->adminStatus)())->toBe('default');
});
