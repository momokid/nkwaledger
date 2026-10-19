<?php

namespace App\Services\Ussd;

use App\Models\PendingEntry;
use App\Models\User;
use App\Services\Loans\LoanListService;
use App\Services\Loans\PendingEntryService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

// what the farmer's agent has recorded and is waiting on them. The farmer answers here as they would on the app card
class UssdWaiting
{
    private const SHOWN = 5;
    private const LENDER_WIDTH = 16;

    public function __construct(
        private readonly LoanListService $list,
        private readonly PendingEntryService $entries,
        private readonly UssdText $text,
    ) {}

    /** @return array<int, array<string, mixed>> numbered from 1 */
    public function items(User $user): array
    {
        return $this->list->pendingFor($user->farmerProfile)->take(self::SHOWN)->values()
            ->mapWithKeys(fn(array $item, int $index) => [$index + 1 => $item])->all();
    }

    public function listScreen(array $items): string
    {
        $lines = collect($items)->map(fn(array $item, int $number) => $this->text->get('waiting_line', [
            'number' => $number,
            'kind' => $this->text->get('kind_' . $item['kind']),
            'lender' => Str::limit($item['lender'], self::LENDER_WIDTH, ''),
            'amount' => Money::format($item['amount']),
        ]))->implode("\n");

        return $this->text->get('waiting_title') . "\n" . $lines;
    }

    public function detailScreen(array $item): string
    {
        return $this->text->get('waiting_detail', [
            'kind' => $this->text->get('kind_' . $item['kind']),
            'lender' => $item['lender'],
            'amount' => Money::format($item['amount']),
            'date' => Carbon::parse($item['date'])->format('d/m/Y'),
            'agent' => $item['agent'],
        ]);
    }

    // the entry must be this farmer's own, whatever uuid the session holds
    public function entryFor(User $user, string $uuid): PendingEntry
    {
        return PendingEntry::where('uuid', $uuid)->where('farmer_profile_id', $user->farmerProfile->id)->firstOrFail();
    }

    // null when a repayment was refused because the loan moved on
    public function accept(User $user, string $uuid): bool
    {
        return $this->entries->accept($this->entryFor($user, $uuid), $user, PendingEntry::VIA_USSD) !== null;
    }

    public function decline(User $user, string $uuid): void
    {
        $this->entries->decline($this->entryFor($user, $uuid), $user, PendingEntry::VIA_USSD);
    }
}
