<?php

namespace App\Exceptions\Loans;

use RuntimeException;

// the loan is not as it was when an agent typed the repayment, so the entry cannot be accepted as it stands
class RepaymentSnapshotChanged extends RuntimeException {}
