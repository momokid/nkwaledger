<?php

namespace App\Services\Ussd;

use App\Contracts\SmsProvider;
use App\Exceptions\Ledger\PostingFailed;
use App\Exceptions\Loans\EntryRefused;
use App\Models\Loan;
use App\Models\PendingEntry;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Loans\LenderService;
use App\Services\Loans\LoanService;
use App\Services\Loans\LoanListService;
use App\Services\Loans\PendingEntryService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

// an agent records a loan or a repayment for one of their farmers, or withdraws one that is still waiting. Whatever is
// recorded waits until the farmer accepts it themselves; nothing here moves any money
class UssdLoanFlow
{
    private const SHOWN = 5;
    private const NAME_WIDTH = 20;

    public function __construct(
        private readonly UssdScreens $screens,
        private readonly UssdText $text,
        private readonly UssdRecorder $recorder,
        private readonly LenderService $lenders,
        private readonly PendingEntryService $entries,
        private readonly LoanListService $list,
        private readonly SmsProvider $sms,
    ) {}

    public function start(string $key, array $state, User $agent, User $farmer): array
    {
        if (! Gate::forUser($agent)->allows('record', [PendingEntry::class, $farmer->farmerProfile])) {
            return $this->screens->end('loan_not_allowed', $key);
        }

        return $this->screens->show($key, $this->at($state, 'loan_menu'), 'loan_menu');
    }

    /** @return array{message: string, continue: bool} */
    public function handle(string $key, array $state, User $agent, User $farmer, string $input): array
    {
        return match ($state['step']) {
            'loan_menu' => $this->menu($key, $state, $agent, $farmer, $input),
            'loan_lender' => $this->lenderTyped($key, $state, $input),
            'loan_lender_pick' => $this->lenderPicked($key, $state, $input),
            'loan_amount' => $this->amountTyped($key, $state, $input),
            'loan_into' => $this->recorder->isMethod($input)
                ? $this->screens->show($key, [...$this->at($state, 'loan_interest'), 'loan_into' => $this->recorder->accountId($input)], 'loan_interest')
                : $this->screens->show($key, $state, 'loan_into'),
            'loan_interest' => $this->interestChoice($key, $state, $input),
            'loan_interest_amount' => $this->interestTyped($key, $state, $input),
            'loan_due' => $this->dueChoice($key, $state, $farmer, $input),
            'loan_due_date' => $this->dueTyped($key, $state, $farmer, $input),
            'loan_farm' => $this->farmPicked($key, $state, $farmer, $input),
            'loan_farm_amount' => $this->farmAmount($key, $state, $farmer, $input),
            'loan_purpose' => $this->purposePicked($key, $state, $farmer, $input),
            'loan_purpose_note' => $this->purposeNote($key, $state, $farmer, $input),
            'loan_farm_more' => $this->moreChoice($key, $state, $farmer, $input),
            'loan_confirm' => $this->confirmed($key, $state, $agent, $farmer, $input),
            'loan_rep_pick' => $this->repaymentPicked($key, $state, $farmer, $input),
            'loan_rep_amount' => $this->repaymentAmount($key, $state, $input),
            'loan_rep_from' => $this->recorder->isMethod($input)
                ? $this->repaymentConfirm($key, [...$state, 'loan_from' => $this->recorder->accountId($input), 'loan_from_choice' => $input], $farmer)
                : $this->screens->show($key, $state, 'method_expense'),
            'loan_rep_confirm' => $this->repaymentSaved($key, $state, $agent, $farmer, $input),
            'loan_pending' => $this->pendingPicked($key, $state, $farmer, $input),
            'loan_pending_detail' => $this->withdrawn($key, $state, $agent, $farmer, $input),
        };
    }

    // one step back, keeping what the agent already entered on the steps before it
    public function back(string $key, array $state, User $agent, User $farmer): array
    {
        return match ($state['step']) {
            'loan_lender' => $this->screens->show($key, $this->at($state, 'loan_menu'), 'loan_menu'),
            'loan_lender_pick' => $this->screens->show($key, $this->at($state, 'loan_lender'), 'loan_lender'),
            'loan_amount' => $this->pickScreen($key, $state),
            'loan_into' => $this->screens->show($key, $this->at($state, 'loan_amount'), 'amount'),
            'loan_interest' => $this->screens->show($key, $this->at($state, 'loan_into'), 'loan_into'),
            'loan_interest_amount' => $this->screens->show($key, $this->at($state, 'loan_interest'), 'loan_interest'),
            'loan_due' => $this->screens->show($key, $this->at($state, 'loan_interest'), 'loan_interest'),
            'loan_due_date' => $this->screens->show($key, $this->at($state, 'loan_due'), 'loan_due'),
            'loan_farm' => ($state['loan_parts'] ?? []) === []
                ? $this->screens->show($key, $this->at($state, 'loan_due'), 'loan_due')
                : $this->moreScreen($key, $state),
            'loan_farm_amount' => $this->farmList($key, $state, $farmer, 0),
            'loan_purpose' => ($state['loan_cur']['asked'] ?? false) ? $this->amountScreen($key, $state) : $this->farmList($key, $state, $farmer, 0),
            'loan_purpose_note' => $this->purposeScreen($key, $state, $farmer, 0),
            'loan_farm_more' => $this->lastPartReopened($key, $state, $farmer),
            'loan_confirm' => ($state['loan_kind'] ?? '') === Loan::KIND_EXISTING
                ? $this->screens->show($key, $this->at($state, 'loan_amount'), 'amount')
                : $this->farmStart($key, $state, $farmer),
            'loan_rep_pick', 'loan_pending' => $this->screens->show($key, $this->at($state, 'loan_menu'), 'loan_menu'),
            'loan_rep_amount' => $this->repaymentList($key, $state, $farmer),
            'loan_rep_from' => $this->screens->show($key, $this->at($state, 'loan_rep_amount'), 'amount'),
            'loan_rep_confirm' => $this->screens->show($key, $this->at($state, 'loan_rep_from'), 'method_expense'),
            'loan_pending_detail' => $this->pendingList($key, $state, $farmer),
            default => $this->screens->show($key, $this->at($state, 'loan_menu'), 'loan_menu'),
        };
    }

    private function menu(string $key, array $state, User $agent, User $farmer, string $input): array
    {
        return match ($input) {
            // a money loan names the farms it will be used on, so a farmer with none cannot be given one
            '1' => $farmer->farmerProfile->farmUnits()->exists()
                ? $this->screens->show($key, [...$this->at($state, 'loan_lender'), 'loan_kind' => Loan::KIND_MONEY], 'loan_lender')
                : $this->screens->end('loan_no_farm', $key),
            '2' => $this->screens->show($key, [...$this->at($state, 'loan_lender'), 'loan_kind' => Loan::KIND_EXISTING], 'loan_lender'),
            '3' => $this->repaymentList($key, $state, $farmer),
            '4' => $this->pendingList($key, $state, $farmer),
            default => $this->screens->show($key, $state, 'loan_menu'),
        };
    }

    // --- a new or existing loan ---

    private function lenderTyped(string $key, array $state, string $input): array
    {
        if (mb_strlen(trim($input)) < LenderService::SEARCH_MIN) {
            return $this->screens->show($key, $state, 'loan_lender');
        }

        $found = $this->lenders->search($input)->take(self::SHOWN)->map(fn(array $lender) => ['uuid' => $lender['uuid'], 'name' => $lender['name']])->values()->all();

        if ($found === []) {
            return $this->screens->show($key, $state, 'loan_lender_none');
        }

        return $this->pickScreen($key, [...$state, 'loan_results' => $found]);
    }

    private function pickScreen(string $key, array $state): array
    {
        $lines = collect($state['loan_results'])->values()->map(fn(array $lender, int $index) => ($index + 1) . '. ' . Str::limit($lender['name'], self::NAME_WIDTH, ''))->implode("\n");

        return $this->screens->show($key, $this->at($state, 'loan_lender_pick'), null, [], $this->text->get('loan_lender_pick') . "\n" . $lines);
    }

    private function lenderPicked(string $key, array $state, string $input): array
    {
        $lender = $state['loan_results'][(int) $input - 1] ?? null;

        return $lender === null
            ? $this->pickScreen($key, $state)
            : $this->screens->show($key, [...$this->at($state, 'loan_amount'), 'loan_lender' => $lender['uuid'], 'loan_lender_name' => $lender['name']], 'amount');
    }

    private function amountTyped(string $key, array $state, string $input): array
    {
        $amount = $this->recorder->amount($input);

        if ($amount === null) {
            return $this->screens->show($key, $state, 'amount_invalid');
        }

        $state = [...$state, 'loan_amount' => $amount];

        return $state['loan_kind'] === Loan::KIND_MONEY
            ? $this->screens->show($key, $this->at($state, 'loan_into'), 'loan_into')
            : $this->loanConfirm($key, $state);
    }

    private function interestChoice(string $key, array $state, string $input): array
    {
        return match ($input) {
            '1' => $this->screens->show($key, [...$this->at($state, 'loan_due'), 'loan_interest' => null], 'loan_due'),
            '2' => $this->screens->show($key, $this->at($state, 'loan_interest_amount'), 'loan_interest_amount'),
            default => $this->screens->show($key, $state, 'loan_interest'),
        };
    }

    private function interestTyped(string $key, array $state, string $input): array
    {
        $interest = $this->recorder->amount($input);

        return $interest === null
            ? $this->screens->show($key, $state, 'amount_invalid')
            : $this->screens->show($key, [...$this->at($state, 'loan_due'), 'loan_interest' => $interest], 'loan_due');
    }

    private function dueChoice(string $key, array $state, User $farmer, string $input): array
    {
        return match ($input) {
            '1' => $this->farmStart($key, [...$state, 'loan_due' => null], $farmer),
            '2' => $this->screens->show($key, $this->at($state, 'loan_due_date'), 'loan_due_date'),
            default => $this->screens->show($key, $state, 'loan_due'),
        };
    }

    // DD/MM/YYYY, a real date, today or later
    private function dueTyped(string $key, array $state, User $farmer, string $input): array
    {
        $due = $this->dueDate($input);

        return $due === null
            ? $this->screens->show($key, $state, 'loan_due_date')
            : $this->farmStart($key, [...$state, 'loan_due' => $due], $farmer);
    }

    private function dueDate(string $input): ?string
    {
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $input, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return null;
        }

        $date = Carbon::create((int) $m[3], (int) $m[2], (int) $m[1])->startOfDay();

        return $date->isBefore(Carbon::today()) ? null : $date->toDateString();
    }

    private function loanConfirm(string $key, array $state): array
    {
        return $this->screens->show($key, $this->at($state, 'loan_confirm'), 'loan_confirm', [
            'kind' => $this->text->get('loan_kind_' . $state['loan_kind']),
            'lender' => $state['loan_lender_name'],
            'amount' => Money::format(Money::toMinor($state['loan_amount'])),
        ]);
    }

    private function confirmed(string $key, array $state, User $agent, User $farmer, string $input): array
    {
        if ($input === '2') {
            return $this->screens->end('not_saved', $key);
        }

        if ($input !== '1') {
            return $this->loanConfirm($key, $state);
        }

        try {
            $entry = $this->entries->recordLoan($farmer->farmerProfile, $agent, [
                'kind' => $state['loan_kind'],
                'lender' => $state['loan_lender'],
                'lender_name' => $state['loan_lender_name'],
                'amount' => $state['loan_amount'],
                'date' => today()->toDateString(),
                'paid_into' => $state['loan_into'] ?? null,
                'interest' => $state['loan_interest'] ?? null,
                'due_date' => $state['loan_due'] ?? null,
                'farms' => $this->farmsOf($state),
            ]);
        } catch (PostingFailed|InvalidArgumentException $refusal) {
            return $this->screens->end(null, $key, $refusal->getMessage());
        }

        $this->tellFarmer($farmer, 'loan', $state['loan_lender_name'], $state['loan_amount']);

        return $this->screens->end(null, $key, $this->text->get('loan_saved', ['name' => trim($farmer->first_name)]));
    }

    // --- the farms a new money loan will be used on ---

    private function farmStart(string $key, array $state, User $farmer): array
    {
        return $this->farmList($key, [...$state, 'loan_parts' => [], 'loan_cur' => null], $farmer, 0);
    }

    // the farmer's farm units not yet given a share, five to a page
    private function farmList(string $key, array $state, User $farmer, int $page): array
    {
        $unused = $this->unusedFarms($state, $farmer);
        $shown = $unused->slice($page * self::SHOWN, self::SHOWN)->values();
        $more = $unused->count() > ($page + 1) * self::SHOWN;

        $lines = $shown->map(fn(array $unit, int $index) => ($index + 1) . '. ' . Str::limit($unit['name'], self::NAME_WIDTH, ''))
            ->when($more, fn($lines) => $lines->push(($shown->count() + 1) . '. ' . $this->text->get('more')))
            ->implode("\n");

        return $this->screens->show(
            $key,
            [...$this->at($state, 'loan_farm'), 'loan_page' => $page, 'loan_page_uuids' => $shown->pluck('uuid')->all(), 'loan_page_more' => $more],
            null,
            [],
            $this->text->get('loan_farm_pick') . "\n" . $lines,
        );
    }

    /** @return \Illuminate\Support\Collection<int, array{uuid: string, name: string}> */
    private function unusedFarms(array $state, User $farmer)
    {
        $taken = array_column($state['loan_parts'] ?? [], 'uuid');

        return $farmer->farmerProfile->farmUnits()->orderBy('name')->get(['uuid', 'name'])
            ->reject(fn($unit) => in_array($unit->uuid, $taken, true))
            ->map(fn($unit) => ['uuid' => $unit->uuid, 'name' => $unit->name])
            ->values();
    }

    private function farmPicked(string $key, array $state, User $farmer, string $input): array
    {
        $index = (int) $input;
        $uuids = $state['loan_page_uuids'] ?? [];

        if ($index === count($uuids) + 1 && ($state['loan_page_more'] ?? false)) {
            return $this->farmList($key, $state, $farmer, ($state['loan_page'] ?? 0) + 1);
        }

        $unit = $index >= 1 ? $this->unusedFarms($state, $farmer)->firstWhere('uuid', $uuids[$index - 1] ?? null) : null;

        if ($unit === null) {
            return $this->farmList($key, $state, $farmer, $state['loan_page'] ?? 0);
        }

        // the last farm to be named takes whatever is left, so the amounts always add up
        if ($this->unusedFarms($state, $farmer)->count() === 1) {
            return $this->purposeScreen($key, [...$state, 'loan_cur' => $unit + ['amount_minor' => $this->leftMinor($state), 'asked' => false]], $farmer, 0);
        }

        return $this->amountScreen($key, [...$state, 'loan_cur' => $unit + ['amount_minor' => 0, 'asked' => true]]);
    }

    private function amountScreen(string $key, array $state): array
    {
        return $this->screens->show($key, $this->at($state, 'loan_farm_amount'), 'loan_farm_amount', ['left' => Money::format($this->leftMinor($state))]);
    }

    private function farmAmount(string $key, array $state, User $farmer, string $input): array
    {
        $amount = $this->recorder->amount($input);

        if ($amount === null) {
            return $this->screens->show($key, $state, 'amount_invalid');
        }

        if (Money::toMinor($amount) > $this->leftMinor($state)) {
            return $this->screens->show($key, $state, 'loan_farm_too_much');
        }

        return $this->purposeScreen($key, [...$state, 'loan_cur' => [...$state['loan_cur'], 'amount_minor' => Money::toMinor($amount)]], $farmer, 0);
    }

    // what is still to be placed on a farm: the loan less the shares already given
    private function leftMinor(array $state): int
    {
        return Money::toMinor($state['loan_amount']) - array_sum(array_column($state['loan_parts'] ?? [], 'amount_minor'));
    }

    // the farmer's spending records, five to a page; "Something else" closes the last page
    private function purposeScreen(string $key, array $state, User $farmer, int $page): array
    {
        $all = TransactionTemplate::loanPurposesFor($farmer->farmerProfile)->map(fn($template) => ['slug' => $template->slug, 'name' => $template->name])->values();
        $shown = $all->slice($page * self::SHOWN, self::SHOWN)->values();
        $more = $all->count() > ($page + 1) * self::SHOWN;

        $lines = $shown->map(fn(array $row, int $index) => ($index + 1) . '. ' . Str::limit($row['name'], self::NAME_WIDTH, ''))
            ->push(($shown->count() + 1) . '. ' . $this->text->get($more ? 'more' : 'loan_purpose_other'))
            ->implode("\n");

        return $this->screens->show(
            $key,
            [...$this->at($state, 'loan_purpose'), 'loan_purpose_page' => $page, 'loan_purpose_slugs' => $shown->pluck('slug')->all(), 'loan_purpose_more' => $more],
            null,
            [],
            $this->text->get('loan_purpose_pick') . "\n" . $lines,
        );
    }

    private function purposePicked(string $key, array $state, User $farmer, string $input): array
    {
        $index = (int) $input;
        $slugs = $state['loan_purpose_slugs'] ?? [];

        if ($index >= 1 && $index <= count($slugs)) {
            return $this->partDone($key, $state, $farmer, $slugs[$index - 1], null);
        }

        if ($index === count($slugs) + 1) {
            return ($state['loan_purpose_more'] ?? false)
                ? $this->purposeScreen($key, $state, $farmer, ($state['loan_purpose_page'] ?? 0) + 1)
                : $this->screens->show($key, $this->at($state, 'loan_purpose_note'), 'loan_purpose_note');
        }

        return $this->purposeScreen($key, $state, $farmer, $state['loan_purpose_page'] ?? 0);
    }

    private function purposeNote(string $key, array $state, User $farmer, string $input): array
    {
        $note = trim($input);

        return $note === ''
            ? $this->screens->show($key, $state, 'loan_purpose_note_empty')
            : $this->partDone($key, $state, $farmer, LoanService::OTHER_PURPOSE, mb_substr($note, 0, 255));
    }

    // one farm is complete: the loan is ready to confirm when nothing is left, otherwise another farm is asked for
    private function partDone(string $key, array $state, User $farmer, string $purpose, ?string $note): array
    {
        $state['loan_parts'] = [...($state['loan_parts'] ?? []), [...$state['loan_cur'], 'purpose' => $purpose, 'purpose_note' => $note]];
        $state['loan_cur'] = null;

        return $this->leftMinor($state) === 0 ? $this->loanConfirm($key, $state) : $this->moreScreen($key, $state);
    }

    private function moreScreen(string $key, array $state): array
    {
        return $this->screens->show($key, $this->at($state, 'loan_farm_more'), 'loan_farm_more', ['left' => Money::format($this->leftMinor($state))]);
    }

    private function moreChoice(string $key, array $state, User $farmer, string $input): array
    {
        if ($input === '1') {
            return $this->farmList($key, $state, $farmer, 0);
        }

        if ($input !== '2') {
            return $this->moreScreen($key, $state);
        }

        // the rest goes on the farm just given a share
        $last = array_key_last($state['loan_parts']);
        $state['loan_parts'][$last]['amount_minor'] += $this->leftMinor($state);

        return $this->loanConfirm($key, $state);
    }

    // back from "another farm?": the farm just finished is open again, at its purpose
    private function lastPartReopened(string $key, array $state, User $farmer): array
    {
        $cur = array_pop($state['loan_parts']);
        unset($cur['purpose'], $cur['purpose_note']);

        return $this->purposeScreen($key, [...$state, 'loan_cur' => $cur], $farmer, 0);
    }

    /** @return list<array{farm_unit: string, amount: string, purpose: string, purpose_note: ?string}> */
    private function farmsOf(array $state): array
    {
        return array_map(fn(array $part) => [
            'farm_unit' => $part['uuid'],
            'amount' => Money::toDecimal($part['amount_minor']),
            'purpose' => $part['purpose'],
            'purpose_note' => $part['purpose_note'],
        ], $state['loan_parts'] ?? []);
    }

    // --- a repayment ---

    private function repaymentList(string $key, array $state, User $farmer): array
    {
        $loans = Loan::where('farmer_profile_id', $farmer->farmerProfile->id)->where('status', Loan::ACTIVE)->orderByDesc('id')->get();
        $balances = Loan::balancesOf($loans);
        $open = $loans->filter(fn(Loan $loan) => $balances[$loan->id] > 0)->take(self::SHOWN)->values();

        if ($open->isEmpty()) {
            return $this->screens->end('loan_rep_none', $key);
        }

        $lines = $open->map(fn(Loan $loan, int $index) => ($index + 1) . '. ' . Str::limit($loan->lender_name, self::NAME_WIDTH, '') . ' ' . Money::format($balances[$loan->id]))->implode("\n");

        return $this->screens->show($key, [...$this->at($state, 'loan_rep_pick'), 'loan_uuids' => $open->pluck('uuid')->all()], null, [], $this->text->get('loan_rep_pick') . "\n" . $lines);
    }

    private function repaymentPicked(string $key, array $state, User $farmer, string $input): array
    {
        $uuid = $state['loan_uuids'][(int) $input - 1] ?? null;

        return $uuid === null
            ? $this->repaymentList($key, $state, $farmer)
            : $this->screens->show($key, [...$this->at($state, 'loan_rep_amount'), 'loan_uuid' => $uuid], 'amount');
    }

    private function repaymentAmount(string $key, array $state, string $input): array
    {
        $amount = $this->recorder->amount($input);

        return $amount === null
            ? $this->screens->show($key, $state, 'amount_invalid')
            : $this->screens->show($key, [...$this->at($state, 'loan_rep_from'), 'loan_amount' => $amount], 'method_expense');
    }

    private function repaymentConfirm(string $key, array $state, User $farmer): array
    {
        $loan = $this->loanOf($state, $farmer);

        return $this->screens->show($key, $this->at($state, 'loan_rep_confirm'), 'loan_rep_confirm', [
            'lender' => $loan->lender_name,
            'amount' => Money::format(Money::toMinor($state['loan_amount'])),
            'method' => $this->text->get('method_' . $state['loan_from_choice']),
        ]);
    }

    private function repaymentSaved(string $key, array $state, User $agent, User $farmer, string $input): array
    {
        if ($input === '2') {
            return $this->screens->end('not_saved', $key);
        }

        if ($input !== '1') {
            return $this->repaymentConfirm($key, $state, $farmer);
        }

        $loan = $this->loanOf($state, $farmer);

        try {
            $this->entries->recordRepayment($loan, $agent, [
                'amount' => $state['loan_amount'],
                'date' => today()->toDateString(),
                'paid_from' => $state['loan_from'],
                'idempotency_key' => (string) Str::uuid(),
            ]);
        } catch (PostingFailed|EntryRefused|InvalidArgumentException $refusal) {
            return $this->screens->end(null, $key, $refusal->getMessage());
        }

        $this->tellFarmer($farmer, 'repayment', $loan->lender_name, $state['loan_amount']);

        return $this->screens->end(null, $key, $this->text->get('loan_saved', ['name' => trim($farmer->first_name)]));
    }

    private function loanOf(array $state, User $farmer): Loan
    {
        return Loan::where('uuid', $state['loan_uuid'])->where('farmer_profile_id', $farmer->farmerProfile->id)->firstOrFail();
    }

    // --- what is still waiting ---

    private function pendingList(string $key, array $state, User $farmer): array
    {
        $items = $this->list->pendingFor($farmer->farmerProfile)->take(self::SHOWN)->values();

        if ($items->isEmpty()) {
            return $this->screens->end('loan_pending_none', $key);
        }

        $lines = $items->map(fn(array $item, int $index) => $this->text->get('waiting_line', [
            'number' => $index + 1,
            'kind' => $this->text->get('kind_' . $item['kind']),
            'lender' => Str::limit($item['lender'], self::NAME_WIDTH, ''),
            'amount' => Money::format($item['amount']),
        ]))->implode("\n");

        return $this->screens->show($key, [...$this->at($state, 'loan_pending'), 'loan_uuids' => $items->pluck('uuid')->all()], null, [], $this->text->get('loan_pending_title') . "\n" . $lines);
    }

    private function pendingPicked(string $key, array $state, User $farmer, string $input): array
    {
        $uuid = $state['loan_uuids'][(int) $input - 1] ?? null;
        $item = $this->list->pendingFor($farmer->farmerProfile)->firstWhere('uuid', $uuid);

        if ($item === null) {
            return $this->pendingList($key, $state, $farmer);
        }

        return $this->screens->show($key, [...$this->at($state, 'loan_pending_detail'), 'loan_uuid' => $uuid], 'loan_pending_detail', [
            'kind' => $this->text->get('kind_' . $item['kind']),
            'lender' => $item['lender'],
            'amount' => Money::format($item['amount']),
            'date' => Carbon::parse($item['date'])->format('d/m/Y'),
        ]);
    }

    private function withdrawn(string $key, array $state, User $agent, User $farmer, string $input): array
    {
        if ($input !== '1') {
            return $this->pendingPicked($key, [...$state, 'loan_uuids' => [$state['loan_uuid']]], $farmer, '1');
        }

        $entry = PendingEntry::where('uuid', $state['loan_uuid'])->where('farmer_profile_id', $farmer->farmerProfile->id)->firstOrFail();

        try {
            $this->entries->withdraw($entry, $agent);
        } catch (EntryRefused|PostingFailed $refusal) {
            return $this->screens->end(null, $key, $refusal->getMessage());
        }

        return $this->screens->end('loan_withdrawn', $key);
    }

    // the farmer is texted that something is waiting; a failed text never undoes what was recorded
    private function tellFarmer(User $farmer, string $kind, string $lender, string $amount): void
    {
        try {
            $this->sms->send($farmer->phone, $this->text->in($farmer->ussd_language ?? 'en')->get('loan_entry_sms', [
                'kind' => $this->text->in($farmer->ussd_language ?? 'en')->get('kind_' . $kind),
                'amount' => Money::format(Money::toMinor($amount)),
                'lender' => $lender,
            ]));
        } catch (Throwable $failure) {
            report($failure);
        }
    }

    private function at(array $state, string $step): array
    {
        return [...$state, 'step' => $step];
    }
}
