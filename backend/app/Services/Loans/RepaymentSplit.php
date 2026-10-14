<?php

namespace App\Services\Loans;

// pure and integer-only: the one place a repayment is split, for the preview and for posting alike.
// P and I are what was agreed, T their sum; the paid figures are what the books already hold
class RepaymentSplit
{
    /** @return array{principal: int, interest: int, excess: int, remaining_principal: int, remaining_interest: int} */
    public static function compute(int $amount, int $principal, int $interest, int $principalPaid, int $interestPaid): array
    {
        $rp = max(0, $principal - $principalPaid);
        $ri = max(0, $interest - $interestPaid);
        $owed = $rp + $ri;

        if ($amount >= $owed) {
            $excess = $amount - $owed;

            return self::result($rp, $ri + $excess, $excess, $rp, $ri);
        }

        $total = $principal + $interest;
        $share = $interest === 0 ? 0 : self::shareOf($amount, $interest, $total);
        $part = $amount - $share;

        // a part never goes past what remains of it; the overflow goes to the other part
        if ($part > $rp) {
            [$part, $share] = [$rp, $share + $part - $rp];
        } elseif ($share > $ri) {
            [$part, $share] = [$part + $share - $ri, $ri];
        }

        return self::result($part, $share, 0, $rp, $ri);
    }

    // amount x interest / total, rounded half up. Split by the quotient first so the product stays small (safe up to about GHS 10 million a loan)
    private static function shareOf(int $amount, int $interest, int $total): int
    {
        $whole = intdiv($amount, $total);
        $rest = $amount - $whole * $total;

        return $whole * $interest + intdiv(2 * $rest * $interest + $total, 2 * $total);
    }

    private static function result(int $principal, int $interest, int $excess, int $rp, int $ri): array
    {
        return [
            'principal' => $principal,
            'interest' => $interest,
            'excess' => $excess,
            'remaining_principal' => $rp,
            'remaining_interest' => $ri,
        ];
    }
}
