<?php

// The request class must not rely on the framework's global trim: it trims the lender name itself, so a name
// made only of spaces (even no-break spaces) is a missing name wherever the request is used.

use App\Http\Requests\Loans\StoreLoanRequest;
use Illuminate\Validation\ValidationException;

function loanRequest(array $over = []): StoreLoanRequest
{
    $request = StoreLoanRequest::create('/loans', 'POST', array_merge([
        'kind' => 'existing', 'lender_name' => 'Uncle Kofi', 'lender_type' => 'individual', 'amount' => '300',
    ], $over));

    return $request->setContainer(app())->setRedirector(app('redirect'));
}

test('a lender name of only spaces, tabs or no-break spaces gets the approved words', function (string $name) {
    try {
        loanRequest(['lender_name' => $name])->validateResolved();
        $errors = [];
    } catch (ValidationException $failure) {
        $errors = $failure->errors();
    }

    expect($errors['lender_name'] ?? null)->toBe(["Enter the lender's name."]);
})->with(['spaces' => '   ', 'a tab' => "\t", 'a no-break space' => "\u{00A0}", 'mixed' => " \u{00A0}\t "]);

test('a good lender name is trimmed and kept', function () {
    $request = loanRequest(['lender_name' => "  \u{00A0}Uncle Kofi \t"]);
    $request->validateResolved();

    expect($request->validated()['lender_name'])->toBe('Uncle Kofi');
});
