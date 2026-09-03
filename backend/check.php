<?php

App\Models\AccountingPeriod::all()->each(
    fn($p) => print($p->name . ' | ' . $p->starts_on->toDateString() . ' to ' . $p->ends_on->toDateString() . ' | ' . $p->status . PHP_EOL)
);
