<?php

namespace App\Services\Loans;

use App\Contracts\SmsProvider;
use App\Exceptions\Ledger\PostingFailed;
use App\Exceptions\Loans\EntryRefused;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\PendingEntry;
use App\Models\PendingEntryCode;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Money;
use App\Support\Wording;
use Illuminate\Support\Facades\DB;
use Throwable;

// the code that lets a farmer accept an agent's entry without the app. 6 digits from a secure source, kept only as
// a keyed hash, valid 10 minutes, used once, locked after 3 wrong tries, 3 sent per entry per hour.
// The code goes to the SMS service and nowhere else: not a log, an audit row, a notice or an error
class EntryCodeService
{
    public const LIFETIME_MINUTES = 10;
    public const MAX_TRIES = 3;
    public const MAX_SENDS_PER_HOUR = 3;
    public const SMS_LENGTH = 160;

    public const WRONG = 'The code is not correct.';
    public const EXPIRED = 'The code has expired. Send a new code.';
    public const LOCKED = 'Too many wrong tries. Send a new code.';

    // provisional wording, to be approved
    public const NO_PHONE = 'This farmer has no phone number, so a code cannot be sent.';
    public const TOO_MANY_SENDS = 'Too many codes were sent for this loan. Try again in an hour.';

    public function __construct(
        private readonly SmsProvider $sms,
        private readonly PendingEntryService $entries,
        private readonly AuditService $audit,
    ) {}

    public function send(PendingEntry $entry, User $agent): void
    {
        DB::transaction(function () use ($entry, $agent) {
            $locked = $this->entries->lock($entry);
            $this->entries->mustBePending($locked);

            if ($locked->isRepayment() && $locked->payload['excess']) {
                throw EntryRefused::because(RepaymentService::CODE_NOT_ALLOWED);
            }

            $phone = trim((string) $locked->farmerProfile->user?->phone);

            if ($phone === '') {
                throw EntryRefused::because(self::NO_PHONE);
            }

            $sentThisHour = PendingEntryCode::where('pending_entry_id', $locked->id)->where('created_at', '>', now()->subHour())->count();

            if ($sentThisHour >= self::MAX_SENDS_PER_HOUR) {
                throw EntryRefused::because(self::TOO_MANY_SENDS);
            }

            PendingEntryCode::where('pending_entry_id', $locked->id)
                ->whereNull('cancelled_at')
                ->whereNull('used_at')
                ->update(['cancelled_at' => now()]);

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            PendingEntryCode::create([
                'pending_entry_id' => $locked->id,
                'code_hash' => $this->hash($locked, $code),
                'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
                'created_at' => now(),
            ]);

            try {
                $this->sms->send($phone, $this->message($locked, $code));
            } catch (Throwable) {
                // the provider's own error can echo the message, so none of it is kept: the code row goes with this rollback
                throw PostingFailed::system('Something went wrong. Please try again.');
            }

            $this->audit->recordOn('pending_entry.code_sent', $locked, null, ['expires_in_minutes' => self::LIFETIME_MINUTES], $agent);
        });
    }

    // the verdict on a code, and the code is used up when it is right
    public function check(PendingEntry $entry, string $code): string
    {
        [$verdict, $row] = $this->verify($entry, $code);

        if ($verdict === 'ok') {
            $row->forceFill(['used_at' => now()])->save();
        }

        return $verdict;
    }

    // the agent types the code the farmer read out: right means accepted, in one transaction with the code being used
    public function enter(PendingEntry $entry, User $agent, string $code): Loan|LoanRepayment|null
    {
        [$verdict, $row] = $this->verify($entry, $code);

        if ($verdict !== 'ok') {
            throw EntryRefused::because(match ($verdict) {
                'wrong' => self::WRONG,
                'locked' => self::LOCKED,
                default => self::EXPIRED,
            });
        }

        return DB::transaction(function () use ($entry, $agent, $row) {
            $fresh = PendingEntryCode::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();

            if ($fresh->used_at !== null || $fresh->cancelled_at !== null) {
                throw EntryRefused::because(self::EXPIRED);
            }

            // if acceptance is refused, this whole transaction goes back and the code is still good
            $loan = $this->entries->accept($entry, $agent, PendingEntry::VIA_CODE);

            $fresh->forceFill(['used_at' => now()])->save();

            return $loan;
        });
    }

    /** @return array{0: string, 1: ?PendingEntryCode} ok, wrong, locked or expired */
    private function verify(PendingEntry $entry, string $code): array
    {
        return DB::transaction(function () use ($entry, $code) {
            $row = PendingEntryCode::query()
                ->where('pending_entry_id', $entry->id)
                ->whereNull('cancelled_at')
                ->whereNull('used_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return ['expired', null];
            }

            if ($row->attempts >= self::MAX_TRIES) {
                return ['locked', $row];
            }

            if ($row->expires_at->lessThanOrEqualTo(now())) {
                return ['expired', $row];
            }

            if (! hash_equals($row->code_hash, $this->hash($entry, $code))) {
                $row->increment('attempts');

                return [$row->attempts >= self::MAX_TRIES ? 'locked' : 'wrong', $row];
            }

            return ['ok', $row];
        });
    }

    // keyed with the app key and tied to the entry, so a stolen table cannot be read back or moved to another entry
    private function hash(PendingEntry $entry, string $code): string
    {
        return hash_hmac('sha256', $entry->uuid . '|' . $code, (string) config('app.key'));
    }

    // one SMS holds 160 characters: only the name is cut (with ...), so the amount, the code and the rest of the words stay whole.
    // This is for the SMS only; the name on screen and in the database is never cut
    private function fitLender(string $name, int $otherLength): string
    {
        $room = self::SMS_LENGTH - $otherLength;

        return mb_strlen($name) <= $room ? $name : rtrim(mb_substr($name, 0, $room - 3)) . '...';
    }

    private function message(PendingEntry $entry, string $code): string
    {
        $repayment = $entry->isRepayment();
        $amount = Money::toDecimal($entry->payload[$repayment ? 'amount_minor' : 'principal_minor']);

        $build = fn(string $lender) => $repayment
            ? Wording::get('repayment.sms', ['amount' => $amount, 'lender' => $lender, 'code' => $code])
            : "NkwaLedger: Your agent recorded a loan of GHS {$amount} from {$lender}. Give the code {$code} to your agent only if you agree. It works for 10 minutes.";

        return $build($this->fitLender($entry->lenderName(), mb_strlen($build(''))));
    }
}
