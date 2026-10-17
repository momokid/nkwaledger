<?php

namespace App\Support;

// where an agent's loan notification opens: the agent's own page for that loan, with the one thing on it to look at
class AgentLoanLink
{
    public static function to(string $loanUuid, ?string $focus): string
    {
        return "/agent/loans/{$loanUuid}" . ($focus === null ? '' : "?focus={$focus}");
    }
}
