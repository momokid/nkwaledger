<?php

use App\Support\Money;

it('turns whole cedis into pesewas', function () {
    expect(Money::toMinor('250'))->toBe(25000);
});

// the dot is split, never multiplied, because 250.75 times 100 is not 25075 in php
it('turns cedis and pesewas into pesewas', function () {
    expect(Money::toMinor('250.75'))->toBe(25075);
});

it('pads a single decimal place', function () {
    expect(Money::toMinor('250.7'))->toBe(25070);
});

it('reads an amount with no cedis', function () {
    expect(Money::toMinor('.5'))->toBe(50);
    expect(Money::toMinor('0.05'))->toBe(5);
});

it('ignores commas and spaces', function () {
    expect(Money::toMinor(' 1,200.50 '))->toBe(120050);
});

it('accepts a number as well as a string', function () {
    expect(Money::toMinor(250))->toBe(25000);
});

// rounding without saying so is a lie in a ledger
it('refuses more than two decimal places', function () {
    expect(fn() => Money::toMinor('250.755'))
        ->toThrow(InvalidArgumentException::class);
});

// the plus or minus lives in the journal lines
it('refuses a negative amount', function () {
    expect(fn() => Money::toMinor('-250'))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses something that is not a number', function () {
    expect(fn() => Money::toMinor('two fifty'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn() => Money::toMinor(''))
        ->toThrow(InvalidArgumentException::class);

    expect(fn() => Money::toMinor('250.'))
        ->toThrow(InvalidArgumentException::class);
});

it('turns pesewas back into cedis', function () {
    expect(Money::toDecimal(25075))->toBe('250.75');
    expect(Money::toDecimal(25000))->toBe('250.00');
    expect(Money::toDecimal(5))->toBe('0.05');
    expect(Money::toDecimal(0))->toBe('0.00');
});

it('shows an amount the way a farmer reads it', function () {
    expect(Money::format(120050))->toBe('GHS 1,200.50');
    expect(Money::format(5))->toBe('GHS 0.05');
});

// a valid amount here is still not a valid transaction, that check lives elsewhere
it('allows zero on its own', function () {
    expect(Money::toMinor('0'))->toBe(0);
});
