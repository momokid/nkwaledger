<?php

namespace App\Services\Ussd;

use Illuminate\Support\Facades\Cache;

// the one place a screen is put in front of the farmer or agent: the step is kept for the length of the session, and the
// Back and Forgot PIN lines are added where they belong
class UssdScreens
{
    private const SESSION_MINUTES = 5;

    // every screen that waits for an answer after the PIN steps carries "0. Back", which goes one step back
    public const BACK_STEPS = [
        'confirm_pin', 'period', 'group', 'pick', 'unit', 'quantity', 'amount', 'method', 'confirm', 'language', 'waiting_pick', 'waiting_act',
        'change_old', 'change_new', 'change_confirm', 'farmer_phone', 'target_menu', 'pin_for_farmer',
        'loan_menu', 'loan_lender', 'loan_lender_pick', 'loan_amount', 'loan_into', 'loan_interest', 'loan_interest_amount', 'loan_due',
        'loan_due_date', 'loan_confirm', 'loan_rep_pick', 'loan_rep_amount', 'loan_rep_from', 'loan_rep_confirm', 'loan_pending', 'loan_pending_detail',
        'loan_farm', 'loan_farm_amount', 'loan_purpose', 'loan_purpose_note', 'loan_farm_more',
    ];

    public function __construct(private readonly UssdText $text) {}

    /** @return array{message: string, continue: bool} */
    public function show(string $key, array $state, ?string $screen, array $with = [], ?string $message = null): array
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

    /** @return array{message: string, continue: bool} */
    public function end(?string $screen, ?string $key = null, ?string $message = null): array
    {
        if ($key !== null) {
            Cache::forget($key);
        }

        return ['message' => $message ?? $this->text->get($screen), 'continue' => false];
    }
}
