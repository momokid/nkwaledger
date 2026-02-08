<?php

namespace Tests\Feature\Ledger;

use Tests\TestCase;
use App\Models\User;
use App\Models\LedgerTransaction;
use App\Services\Ledger\LedgerPostingService;
use App\Services\Ledger\PeriodLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PeriodLockEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function posting_is_blocked_when_period_is_locked()
    {
        $user = User::factory()->create();

        // Lock the period
        app(PeriodLockService::class)->lockPeriod(
            now()->subMonth()->toDateString(),
            now()->addMonth()->toDateString(),
            $user->id
        );

        $transaction = LedgerTransaction::create([
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => 200,
            'transaction_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        $this->expectException(\DomainException::class);

        app(LedgerPostingService::class)->post($transaction);
    }
}
