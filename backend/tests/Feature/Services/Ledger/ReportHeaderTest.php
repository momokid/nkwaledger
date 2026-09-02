<?php

use App\Models\FarmerProfile;
use App\Services\Ledger\Reports\ReportHeader;

beforeEach(function () {
    config(['app.report_secret' => 'testing-secret']);

    $this->profile = FarmerProfile::factory()->create();

    $this->make = function (array $overrides = []) {
        return ReportHeader::make(...array_merge([
            'title' => 'Trial Balance',
            'farmerProfile' => $this->profile,
            'from' => '2026-01-01',
            'to' => '2026-12-31',
            'includeProvisional' => false,
            'figures' => ['debit' => 25000, 'credit' => 25000],
        ], $overrides));
    };
});

it('carries the report title', function () {
    expect(($this->make)()->title)->toBe('Trial Balance');
});

it('names the farmer the report is about', function () {
    $header = ($this->make)();

    expect($header->farmerName)->not->toBeEmpty();
    expect($header->farmerReference)->toBe($this->profile->uuid);
});

it('says the period it covers', function () {
    $header = ($this->make)();

    expect($header->from)->toBe('2026-01-01');
    expect($header->to)->toBe('2026-12-31');
});

// a reader has to know whether anything was left out
it('says whether records waiting on approval are in or out', function () {
    expect(($this->make)()->includeProvisional)->toBeFalse();
    expect(($this->make)(['includeProvisional' => true])->includeProvisional)->toBeTrue();
});

it('is always prepared by the system', function () {
    expect(($this->make)()->preparedBy)->toBe('NkwaLedger');
});

it('says when it was made', function () {
    expect(($this->make)()->generatedAt)->not->toBeNull();
});

// the code proves nobody changed a figure after printing
it('carries a verification code', function () {
    expect(($this->make)()->verificationCode)->toMatch('/^[A-Z0-9]{12}$/');
});

it('gives the same code for the same report', function () {
    expect(($this->make)()->verificationCode)->toBe(($this->make)()->verificationCode);
});

it('gives a different code when a figure changes', function () {
    $first = ($this->make)();
    $second = ($this->make)(['figures' => ['debit' => 25000, 'credit' => 20000]]);

    expect($second->verificationCode)->not->toBe($first->verificationCode);
});

it('gives a different code when the dates change', function () {
    $first = ($this->make)();
    $second = ($this->make)(['to' => '2026-06-30']);

    expect($second->verificationCode)->not->toBe($first->verificationCode);
});

it('gives a different code for a different farmer', function () {
    $first = ($this->make)();
    $second = ($this->make)(['farmerProfile' => FarmerProfile::factory()->create()]);

    expect($second->verificationCode)->not->toBe($first->verificationCode);
});

it('gives a different code when provisional records are let in', function () {
    $first = ($this->make)();
    $second = ($this->make)(['includeProvisional' => true]);

    expect($second->verificationCode)->not->toBe($first->verificationCode);
});

// a code anyone can work out is not a code
it('gives a different code under a different secret', function () {
    $first = ($this->make)();

    config(['app.report_secret' => 'another-secret']);

    expect(($this->make)()->verificationCode)->not->toBe($first->verificationCode);
});

// the moment it was printed must not change the code
it('gives the same code no matter when it was made', function () {
    $first = ($this->make)();

    $this->travel(3)->days();

    expect(($this->make)()->verificationCode)->toBe($first->verificationCode);
});

it('refuses to build without a secret', function () {
    config(['app.report_secret' => null]);

    expect(fn() => ($this->make)())->toThrow(RuntimeException::class);
});

it('carries a notice line the report can print', function () {
    expect(($this->make)()->notice)->toBeString();
});
