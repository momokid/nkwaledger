<?php

use App\Models\Lender;

// an institution in the lender list, found by name or made: how a test gets "a lender called X"
function institutionNamed(string $name, string $type = 'microfinance'): Lender
{
    return Lender::where('name_key', Lender::keyOf($name))->first()
        ?? Lender::create(['type' => $type, 'name' => $name]);
}
