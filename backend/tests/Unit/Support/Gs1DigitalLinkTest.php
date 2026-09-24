<?php

use App\Support\Gs1DigitalLink;

test('a full digital link url with gtin, batch and expiry parses all three', function () {
    $result = Gs1DigitalLink::parse('https://id.gs1.org/01/00012345678905/10/BATCH42/17/261231');

    expect($result)->not->toBeNull()
        ->and($result['gtin'])->toBe('00012345678905')
        ->and($result['batch'])->toBe('BATCH42')
        ->and($result['expiry']->toDateString())->toBe('2026-12-31');
});

test('a bracketed application-identifier string parses the same way', function () {
    $result = Gs1DigitalLink::parse('(01)00012345678905(10)BATCH42(17)261231');

    expect($result)->not->toBeNull()
        ->and($result['gtin'])->toBe('00012345678905')
        ->and($result['batch'])->toBe('BATCH42')
        ->and($result['expiry']->toDateString())->toBe('2026-12-31');
});

test('a gtin with no batch or expiry still parses', function () {
    $result = Gs1DigitalLink::parse('https://id.gs1.org/01/00012345678905');

    expect($result)->not->toBeNull()
        ->and($result['gtin'])->toBe('00012345678905')
        ->and($result['batch'])->toBeNull()
        ->and($result['expiry'])->toBeNull();
});

test('arbitrary qr content with no gtin is not a digital link', function () {
    expect(Gs1DigitalLink::parse('https://example.com/some/random/page'))->toBeNull();
});

test('plain typed text is not a digital link', function () {
    expect(Gs1DigitalLink::parse('just some text a supplier typed'))->toBeNull();
});

test('an empty string is not a digital link', function () {
    expect(Gs1DigitalLink::parse(''))->toBeNull();
});

// the whole point of this class is that it never treats scanned content as a url to fetch
test('parsing never performs any network call, even for a url-shaped value', function () {
    $start = microtime(true);

    Gs1DigitalLink::parse('https://this-domain-does-not-resolve-at-all.invalid/01/00012345678905');

    // a real HTTP attempt against an unresolvable host would take much longer than this
    expect(microtime(true) - $start)->toBeLessThan(1.0);
});
