<?php

// The loan form sends the picked lender's public id. A typed name or type beside it is never part of what is validated.

require_once __DIR__ . '/../../Support/Institutions.php';

use App\Http\Requests\Loans\StoreLoanRequest;
use Illuminate\Validation\ValidationException;

function loanRequest(array $over = []): StoreLoanRequest
{
    $request = StoreLoanRequest::create('/loans', 'POST', array_merge([
        'kind' => 'existing', 'lender_name' => 'Uncle Kofi', 'lender' => institutionNamed('Uncle Kofi', 'other')->uuid, 'amount' => '300',
    ], $over));

    return $request->setContainer(app())->setRedirector(app('redirect'));
}

test('a missing or unpicked lender gets the lender placeholder, whatever name was typed', function (mixed $lender) {
    try {
        loanRequest(['lender' => $lender, 'lender_name' => 'Uncle Kofi'])->validateResolved();
        $errors = [];
    } catch (ValidationException $failure) {
        $errors = $failure->errors();
    }

    expect($errors['lender'] ?? null)->toBe(['Lender is not listed']);
})->with(['empty' => '', 'null' => null, 'not an id' => 'Uncle Kofi']);

test('a name or type typed beside the picked lender is not part of what is validated', function () {
    $request = loanRequest(['lender_name' => 'Anything At All', 'lender_type' => 'bank']);
    $request->validateResolved();

    expect($request->validated())->toHaveKey('lender')->not->toHaveKey('lender_name')->not->toHaveKey('lender_type');
});
