<?php

namespace App\Services\Ussd;

use App\Exceptions\Ledger\PostingFailed;
use App\Exceptions\Loans\EntryRefused;
use App\Models\User;
use App\Support\Money;
use App\Support\Phone;
use App\Support\Wording;
use Illuminate\Support\Facades\Cache;

// one reply per call. The step the farmer is on is kept in the cache for the length of the session, and nothing typed is logged
class UssdFlow
{
    private const SESSION_MINUTES = 5;

    // every screen that waits for an answer after the PIN steps carries "0. Back", which goes one step back
    private const BACK_STEPS = ['confirm_pin', 'period', 'group', 'pick', 'unit', 'quantity', 'amount', 'method', 'confirm', 'language', 'waiting_pick', 'waiting_act', 'change_old', 'change_new', 'change_confirm'];

    public function __construct(
        private readonly UssdPinService $pins,
        private readonly UssdText $text,
        private readonly UssdReads $reads,
        private readonly UssdRecorder $recorder,
        private readonly UssdWaiting $waiting,
        private readonly UssdPinReset $pinReset,
    ) {}

    /** @return array{message: string, continue: bool} */
    public function handle(string $session, string $msisdn, bool $new, string $input): array
    {
        $key = 'ussd:' . $session;
        $state = $new ? null : Cache::get($key);
        $user = $state === null ? $this->farmerFor($msisdn) : User::find($state['user']);

        // English until the farmer is known, so a stranger's number never shows which language a farmer chose
        $this->text->use(null);

        // a number that is not a farmer's, a different number from the one the session began with, or a block, all read the same
        if ($user === null || ($state !== null && Phone::normalise($msisdn) !== $user->phone)) {
            Cache::forget($key);

            return $this->end('not_registered');
        }

        $this->text->use($user->ussd_language);

        if ($state === null) {
            if ($this->pins->isLocked($user)) {
                return $this->end('pin_locked');
            }

            $step = $user->ussd_pin_hash === null ? 'set_pin' : 'enter_pin';

            return $this->show($key, ['user' => $user->id, 'step' => $step], $step);
        }

        if ($input === '0' && in_array($state['step'], self::BACK_STEPS, true)) {
            return $this->back($key, $state, $user);
        }

        return match ($state['step']) {
            'set_pin' => $this->pins->isAllowed($input)
                ? $this->show($key, [...$state, 'step' => 'confirm_pin', 'hash' => $this->pins->hashOf($input)], 'confirm_pin')
                : $this->show($key, $state, 'pin_rule'),
            'confirm_pin' => $this->confirm($key, $state, $user, $input),
            'enter_pin' => $this->enter($key, $state, $user, $input),
            'force_new' => $this->newPinEntered($key, $state, $user, $input, 'force_confirm'),
            'force_confirm' => $this->newPinConfirmed($key, $state, $user, $input, 'force_new'),
            'change_old' => $this->currentPinEntered($key, $state, $user, $input),
            'change_new' => $this->newPinEntered($key, $state, $user, $input, 'change_confirm'),
            'change_confirm' => $this->newPinConfirmed($key, $state, $user, $input, 'change_new'),
            'period' => $this->reads->isPeriod($input)
                ? $this->end(null, $key, $this->reads->summary($user->farmerProfile, $input))
                : $this->show($key, $state, 'period'),
            'group' => $this->groupChosen($key, $state, $user, $input),
            'pick' => $this->picked($key, $state, $user, $input),
            'unit' => $this->unitPicked($key, $state, $input),
            'quantity' => $this->quantityEntered($key, $state, $input),
            'amount' => $this->amountEntered($key, $state, $input),
            'method' => $this->recorder->isMethod($input, $state['kind'] === 'sale')
                ? $this->confirmScreen($key, [...$state, 'method' => $input])
                : $this->show($key, $state, $this->methodScreen($state)),
            'language' => $this->languageChosen($key, $state, $user, $input),
            'waiting_pick' => $this->waitingPicked($key, $state, $user, $input),
            'waiting_act' => $this->waitingAnswered($key, $state, $user, $input),
            'confirm' => $this->finish($key, $state, $user, $input, $session),
            default => $this->choose($key, $state, $user, $input),
        };
    }

    private function choose(string $key, array $state, User $user, string $input): array
    {
        return match ($input) {
            '1' => $this->show($key, [...$state, 'step' => 'period'], 'period'),
            '2' => $this->end(null, $key, $this->reads->loans($user->farmerProfile)),
            '3', '4' => $this->startLogging($key, $state, $user, $input === '3' ? 'income' : 'expense'),
            '5' => $this->show($key, [...$state, 'step' => 'language'], 'language_pick'),
            '6' => $this->end('goodbye', $key),
            '7' => $this->show($key, [...$state, 'step' => 'change_old'], 'change_old'),
            '8' => $this->waitingOn() ? $this->startWaiting($key, $state, $user) : $this->menu($key, $state, $user),
            default => $this->menu($key, $state, $user),
        };
    }

    private function languageChosen(string $key, array $state, User $user, string $input): array
    {
        if (! in_array($input, ['1', '2'], true)) {
            return $this->show($key, $state, 'language_pick');
        }

        $language = $input === '2' ? 'tw' : null;
        $user->forceFill(['ussd_language' => $language])->save();
        $this->text->use($language);

        return $this->menu($key, $state, $user);
    }

    // one step back, keeping what the farmer already chose on the steps before it
    private function back(string $key, array $state, User $user): array
    {
        return match ($state['step']) {
            'confirm_pin' => $this->show($key, ['user' => $user->id, 'step' => 'set_pin'], 'set_pin'),
            'change_confirm' => $this->show($key, ['user' => $user->id, 'step' => 'change_new'], 'change_new'),
            'pick' => $state['kind'] === 'expense' ? $this->menu($key, $state, $user) : $this->groupScreen($key, $state),
            'unit' => $this->show($key, [...$state, 'step' => 'pick'], null, [], $this->pickList($state['kind'], $user)),
            'quantity' => count($state['units'] ?? []) > 1
                ? $this->show($key, [...$state, 'step' => 'unit'], null, [], $this->unitList($state['units'], $user))
                : $this->show($key, [...$state, 'step' => 'pick'], null, [], $this->pickList($state['kind'], $user)),
            'amount' => $state['kind'] === 'sale'
                ? $this->show($key, [...$state, 'step' => 'quantity'], $this->quantityScreen($state))
                : $this->show($key, [...$state, 'step' => 'pick'], null, [], $this->pickList($state['kind'], $user)),
            'method' => $this->show($key, [...$state, 'step' => 'amount'], 'amount'),
            'confirm' => $this->show($key, [...$state, 'step' => 'method'], $this->methodScreen($state)),
            'waiting_act' => $this->startWaiting($key, ['user' => $user->id], $user),
            default => $this->menu($key, $state, $user),
        };
    }

    private function startWaiting(string $key, array $state, User $user): array
    {
        $items = $this->waiting->items($user);

        if ($items === []) {
            return $this->end('waiting_none', $key);
        }

        $state = [...$state, 'step' => 'waiting_pick', 'uuids' => array_values(array_map(fn($item) => $item['uuid'], $items))];

        return $this->show($key, $state, null, [], $this->waiting->listScreen($items));
    }

    private function waitingPicked(string $key, array $state, User $user, string $input): array
    {
        $items = $this->waiting->items($user);
        $uuid = $state['uuids'][(int) $input - 1] ?? null;
        $item = collect($items)->firstWhere('uuid', $uuid);

        if ($item === null) {
            return $items === [] ? $this->end('waiting_gone', $key) : $this->show($key, $state, null, [], $this->waiting->listScreen($items));
        }

        return $this->show($key, [...$state, 'step' => 'waiting_act', 'uuid' => $uuid], null, [], $this->waiting->detailScreen($item));
    }

    private function waitingAnswered(string $key, array $state, User $user, string $input): array
    {
        if (! in_array($input, ['1', '2'], true)) {
            $item = collect($this->waiting->items($user))->firstWhere('uuid', $state['uuid']);

            return $item === null ? $this->end('waiting_gone', $key) : $this->show($key, $state, null, [], $this->waiting->detailScreen($item));
        }

        try {
            if ($input === '2') {
                $this->waiting->decline($user, $state['uuid']);

                return $this->end('declined', $key);
            }

            return $this->waiting->accept($user, $state['uuid'])
                ? $this->end('accepted', $key)
                : $this->end(null, $key, Wording::get('repayment.stale'));
        } catch (PostingFailed|EntryRefused $refusal) {
            return $this->end(null, $key, $refusal->getMessage());
        }
    }

    private function startLogging(string $key, array $state, User $user, string $kind): array
    {
        if (($reason = $this->recorder->blockedReason($user)) !== null) {
            return $this->end(null, $key, $reason);
        }

        // money in has two groups: sales (a unit and a quantity) and everything else
        return $kind === 'income'
            ? $this->groupScreen($key, [...$state, 'kind' => 'income'])
            : $this->show($key, [...$state, 'step' => 'pick', 'kind' => $kind], null, [], $this->pickList($kind, $user));
    }

    private function groupScreen(string $key, array $state): array
    {
        return $this->show($key, [...$state, 'step' => 'group'], 'group_income');
    }

    private function groupChosen(string $key, array $state, User $user, string $input): array
    {
        if (! in_array($input, ['1', '2'], true)) {
            return $this->groupScreen($key, $state);
        }

        $kind = $input === '1' ? 'sale' : 'income';

        return $this->show($key, [...$state, 'step' => 'pick', 'kind' => $kind], null, [], $this->pickList($kind, $user));
    }

    private function pickList(string $kind, User $user): string
    {
        $lines = $this->recorder->templates($kind, $user->farmerProfile)->map(fn($template, $number) => "{$number}. {$template->name}")->implode("\n");

        return $this->text->get('pick_' . $kind) . "\n" . $lines;
    }

    private function picked(string $key, array $state, User $user, string $input): array
    {
        $template = $this->recorder->templates($state['kind'], $user->farmerProfile)->get($input);

        if ($template === null) {
            return $this->show($key, $state, null, [], $this->pickList($state['kind'], $user));
        }

        $state = [...$state, 'slug' => $template->slug];

        if (! $template->requires_farm_unit) {
            return $this->show($key, [...$state, 'step' => 'amount'], 'amount');
        }

        $units = $this->recorder->units($user);

        return match (count($units)) {
            0 => $this->end('no_unit', $key),
            1 => $this->show($key, [...$state, 'step' => 'quantity', 'units' => array_keys($units), 'unit' => array_key_first($units)], $this->quantityScreen($state)),
            default => $this->show($key, [...$state, 'step' => 'unit', 'units' => array_keys($units)], null, [], $this->unitList(array_keys($units), $user)),
        };
    }

    /** @param array<int, int> $ids */
    private function unitList(array $ids, User $user): string
    {
        $names = $this->recorder->units($user);
        $lines = collect($ids)->values()->map(fn(int $id, int $index) => ($index + 1) . '. ' . ($names[$id] ?? ''))->implode("\n");

        return $this->text->get('unit_pick') . "\n" . $lines;
    }

    private function unitPicked(string $key, array $state, string $input): array
    {
        $id = $state['units'][(int) $input - 1] ?? null;

        if ($id === null) {
            return $this->show($key, $state, null, [], $this->unitList($state['units'], User::find($state['user'])));
        }

        return $this->show($key, [...$state, 'step' => 'quantity', 'unit' => $id], $this->quantityScreen($state));
    }

    // animals, eggs and fish are counted; produce is measured
    private function quantityScreen(array $state): string
    {
        return $state['slug'] === 'produce_sale' ? 'quantity_much' : 'quantity_many';
    }

    private function quantityEntered(string $key, array $state, string $input): array
    {
        $quantity = $this->recorder->quantity($input);

        return $quantity === null
            ? $this->show($key, $state, $this->quantityScreen($state))
            : $this->show($key, [...$state, 'step' => 'amount', 'quantity' => $quantity], 'amount');
    }

    private function amountEntered(string $key, array $state, string $input): array
    {
        $amount = $this->recorder->amount($input);

        return $amount === null
            ? $this->show($key, $state, 'amount_invalid')
            : $this->show($key, [...$state, 'step' => 'method', 'amount' => $amount], $this->methodScreen($state));
    }

    private function methodScreen(array $state): string
    {
        return 'method_' . $state['kind'];
    }

    private function confirmScreen(string $key, array $state): array
    {
        $state['step'] = 'confirm';
        $with = [
            'name' => $this->recorder->find($state['slug'])->name,
            'amount' => Money::format(Money::toMinor($state['amount'])),
            'method' => $this->text->get('method_' . $state['method']),
            'quantity' => $state['quantity'] ?? '',
        ];

        return $this->show($key, $state, $state['kind'] === 'sale' ? 'confirm_sale' : 'confirm', $with);
    }

    private function finish(string $key, array $state, User $user, string $input, string $session): array
    {
        if ($input !== '1') {
            return $this->end('not_saved', $key);
        }

        try {
            $transaction = $this->recorder->save($user, $this->recorder->find($state['slug']), $state['amount'], $state['method'], $session, $state['unit'] ?? null, $state['quantity'] ?? null);
        } catch (PostingFailed $failure) {
            return $this->end(null, $key, $failure->getMessage());
        }

        return $this->end(null, $key, $this->text->get('saved', ['reference' => $transaction->reference]));
    }

    private function confirm(string $key, array $state, User $user, string $input): array
    {
        if (! $this->pins->matches($input, $state['hash'])) {
            return $this->show($key, ['user' => $user->id, 'step' => 'set_pin'], 'pin_mismatch');
        }

        $this->pins->save($user, $state['hash']);

        return $this->menu($key, ['user' => $user->id], $user);
    }

    private function enter(string $key, array $state, User $user, string $input): array
    {
        if ($this->pins->isLocked($user)) {
            return $this->end('pin_locked', $key);
        }

        // a PIN is four digits, so a lone 0 can only mean the farmer forgot it
        if ($input === '0') {
            return $this->end(match ($this->pinReset->request($user)) {
                'sent' => 'forgot_sent',
                'already' => 'forgot_already',
                default => 'forgot_no_agent',
            }, $key);
        }

        if ($this->pins->check($user, $input)) {
            return $user->ussd_pin_must_change ? $this->show($key, ['user' => $user->id, 'step' => 'force_new'], 'force_new') : $this->menu($key, $state, $user);
        }

        return $this->pins->isLocked($user) ? $this->end('pin_locked', $key) : $this->show($key, $state, 'wrong_pin');
    }

    // Change PIN: the current PIN is checked first, and a wrong one counts towards the lock
    private function currentPinEntered(string $key, array $state, User $user, string $input): array
    {
        if ($this->pins->isLocked($user)) {
            return $this->end('pin_locked', $key);
        }

        if ($this->pins->check($user, $input)) {
            return $this->show($key, ['user' => $user->id, 'step' => 'change_new'], 'change_new');
        }

        return $this->pins->isLocked($user) ? $this->end('pin_locked', $key) : $this->show($key, $state, 'change_old');
    }

    // a new PIN must be allowed and must not be the one it replaces
    private function newPinEntered(string $key, array $state, User $user, string $input, string $next): array
    {
        if (! $this->pins->isAllowed($input)) {
            return $this->show($key, $state, 'pin_rule');
        }

        if ($this->pins->matches($input, (string) $user->ussd_pin_hash)) {
            return $this->show($key, $state, 'pin_same');
        }

        return $this->show($key, [...$state, 'step' => $next, 'hash' => $this->pins->hashOf($input)], 'confirm_pin');
    }

    private function newPinConfirmed(string $key, array $state, User $user, string $input, string $again): array
    {
        if (! $this->pins->matches($input, $state['hash'])) {
            return $this->show($key, ['user' => $user->id, 'step' => $again], 'pin_mismatch');
        }

        $this->pins->save($user, $state['hash']);

        return $again === 'force_new' ? $this->menu($key, $state, $user) : $this->end('pin_changed', $key);
    }

    private function menu(string $key, array $state, User $user): array
    {
        $menu = $this->text->get('menu', ['name' => $user->first_name]);

        return $this->show($key, ['user' => $user->id, 'step' => 'menu'], null, [], $this->waitingOn() ? $menu . "\n" . $this->text->get('menu_waiting') : $menu);
    }

    // "Waiting for you" is built and tested but off the menu until the owner turns it on
    private function waitingOn(): bool
    {
        return (bool) config('services.ussd.waiting');
    }

    private function show(string $key, array $state, ?string $screen, array $with = [], ?string $message = null): array
    {
        Cache::put($key, $state, now()->addMinutes(self::SESSION_MINUTES));

        $text = $message ?? $this->text->get($screen, $with);

        if (in_array($state['step'] ?? null, self::BACK_STEPS, true)) {
            $text .= "\n" . $this->text->get('back');
        }

        if (($state['step'] ?? null) === 'enter_pin') {
            $text .= "\n" . $this->text->get('forgot_hint');
        }

        return ['message' => $text, 'continue' => true];
    }

    private function end(?string $screen, ?string $key = null, ?string $message = null): array
    {
        if ($key !== null) {
            Cache::forget($key);
        }

        return ['message' => $message ?? $this->text->get($screen), 'continue' => false];
    }

    // only an active farmer with a verified number gets in
    private function farmerFor(string $msisdn): ?User
    {
        $phone = Phone::normalise($msisdn);

        if ($phone === null) {
            return null;
        }

        return User::query()
            ->where('phone', $phone)
            ->where('is_active', true)
            ->whereNull('locked_at')
            ->whereNotNull('phone_verified_at')
            ->whereHas('farmerProfile')
            ->role('farmer')
            ->first();
    }
}
