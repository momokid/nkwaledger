<?php

namespace App\Services\Ledger\Reports;

use App\Enums\MoneyClass;
use App\Models\Transaction;

// pure: every input arrives as a parameter, nothing is looked up here
class MoneyClassifier
{
    public function classify(Transaction $transaction, int $moneyInMinor, int $moneyOutMinor, ?Transaction $original): ?MoneyClass
    {
        if ($moneyInMinor === 0 && $moneyOutMinor === 0) {
            return null;
        }

        if ($transaction->transaction_type === Transaction::LOSS) {
            return null;
        }

        // a settlement or a correction is classified by what it settles or corrects,
        // never by its own template (payment_received/payment_made/correction say nothing)
        $subject = $transaction->transaction_type === Transaction::ADJUSTMENT
            ? $original
            : $transaction;

        if ($subject === null) {
            return null;
        }

        return match ($subject->transaction_type) {
            Transaction::INCOME => $subject->template?->is_liability
                ? MoneyClass::Liability
                : MoneyClass::Income,
            Transaction::EXPENSE => $subject->template?->is_stock_purchase
                ? MoneyClass::Asset
                : MoneyClass::Expenditure,
            default => null,
        };
    }
}
