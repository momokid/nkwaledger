<?php

namespace App\Enums;

enum MoneyClass: string
{
    case Asset = 'asset';
    case Expenditure = 'expenditure';
    case Income = 'income';
    case Liability = 'liability';
    case LoanRepayment = 'loan_repayment';

    // the words a person reads: the case name for the old classes, spaced for the new one
    public function label(): string
    {
        return match ($this) {
            self::LoanRepayment => 'Loan repayment',
            default => $this->name,
        };
    }
}
