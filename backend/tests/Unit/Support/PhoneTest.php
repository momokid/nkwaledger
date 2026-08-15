<?php

use App\Support\Phone;

test('a local number becomes international', function () {
    expect(Phone::normalise('0244445566'))->toBe('+233244445566');
});

test('a number already in international form is unchanged', function () {
    expect(Phone::normalise('+233244445566'))->toBe('+233244445566');
});

test('a country code without the plus gets one', function () {
    expect(Phone::normalise('233244445566'))->toBe('+233244445566');
});

test('the dialled international prefix is handled', function () {
    expect(Phone::normalise('00233244445566'))->toBe('+233244445566');
});

test('spaces, dashes and brackets are ignored', function (string $raw) {
    expect(Phone::normalise($raw))->toBe('+233244445566');
})->with([
    '024 444 5566',
    '024-444-5566',
    '+233 24 444 5566',
    ' 0244445566 ',
    '(024) 444 5566',
]);

test('every ghanaian mobile prefix is accepted', function (string $prefix) {
    expect(Phone::normalise($prefix . '4445566'))->toBe('+233' . substr($prefix, 1) . '4445566');
})->with([
    '020',
    '023',
    '024',
    '025',
    '026',
    '027',
    '028',
    '050',
    '053',
    '054',
    '055',
    '056',
    '057',
    '059',
]);

test('a landline is rejected because a code cannot reach it', function () {
    expect(Phone::normalise('0302445566'))->toBeNull();
});

test('a foreign number is rejected', function (string $raw) {
    expect(Phone::normalise($raw))->toBeNull();
})->with([
    '+2348012345566',
    '+447700900123',
    '+1202555017',
]);

test('a wrong length is rejected', function (string $raw) {
    expect(Phone::normalise($raw))->toBeNull();
})->with([
    '024444556',
    '02444455667',
    '024',
    '+23324444556',
]);

test('letters and junk are rejected', function (string $raw) {
    expect(Phone::normalise($raw))->toBeNull();
})->with([
    'not a phone',
    '024444556a',
    '0244-CALL',
]);

test('nothing is rejected', function (?string $raw) {
    expect(Phone::normalise($raw))->toBeNull();
})->with([null, '', '   ']);

// running it twice must not corrupt an already clean number
test('normalising is repeatable', function () {
    $once = Phone::normalise('0244445566');

    expect(Phone::normalise($once))->toBe($once);
});
