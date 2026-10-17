<?php

// The approved wording lives in one file, resources/wording.json. Every key must have exactly the approved text,
// and no raw key may be left in the code.

use App\Support\Wording;

const APPROVED_WORDING = [
    'repayment.date' => 'Repayment Date',
    'repayment.amount' => 'Repayment Amount',
    'repayment.paid_from' => 'Paid From',
    'repayment.principal_part' => 'Principal on Repayment',
    'repayment.interest_part' => 'Interest on Repayment',
    'repayment.save' => 'Save Repayment',
    'repayment.saving' => 'Saving...',
    'repayment.cancel' => 'Cancel',
    'repayment.pending_notice' => 'Your agent recorded a repayment for you. It is not counted until you accept it.',
    'repayment.rejected' => 'Repayment rejected',
    'repayment.accepted' => 'Repayment accepted.',
    'repayment.declined' => 'The farmer declined the repayment.',
    'repayment.stale' => 'The loan changed, so this repayment was not accepted.',
    'repayment.loan_not_active' => 'This loan is not active, so it cannot take a repayment.',
    'repayment.over_total' => 'The amount is more than the loan still owes',
    'repayment.fully_repaid' => 'This loan is fully repaid',
    'repayment.paid_from_invalid' => 'Choose cash, MoMo or bank.',
    'repayment.date_before_loan' => 'Repayment date cannot be before the loan date',
    'repayment.sms' => 'Your agent recorded a repayment of GHS {amount} to {lender}. Code: {code}',
    'repayment.entry' => 'Repayment',
    'lender.name' => 'Lender Name',
    'lender.type' => 'Lender Type',
    'lender.add' => 'Add Lender',
    'lender.edit' => 'Edit',
    'lender.save' => 'Save',
    'lender.cancel' => 'Cancel',
    'lender.saved' => 'Lender details saved',
    'lender.save_failed' => 'Failed to save lender details',
    'lender.not_in_list' => 'Lender is not listed',
    'lender.name_required' => 'Enter the Lender\'s name',
    'lender.type_invalid' => 'Invalid lender details',
    'lender.institution_exists' => 'Lender already exists',
    'lender.name_characters' => 'Use only letters, numbers and common symbols in the Lender\'s name',
    'lender.type.bank' => 'Bank',
    'lender.type.rural_bank' => 'Rural Bank',
    'lender.type.credit_financial_institution' => 'Credit Financial Institution',
    'lender.type.microfinance' => 'Microfinance',
    'lender.type.other' => 'Other',
    'lender.nothing_to_save' => "Enter the Lender's name or choose a type",
    'lender.check_heading' => 'Confirm with the lender',
    'lender.check_status' => 'Status',
    'lender.check_note' => 'Note',
    'lender.check_history' => 'Checks with the lender',
    'lender.check_option_confirmed' => 'Confirmed',
    'lender.check_option_not_confirmed' => 'Not Confirmed',
    'lender.check_option_does_not_match' => 'Does not match',
    'lender.check_option_unreachable' => 'Lender could not be reached',
    'lender.check_line_confirmed' => 'Confirmed with the lender on {date}',
    'lender.check_line_not_confirmed' => 'Not Confirmed',
    'lender.check_line_does_not_match' => 'The lender says this does not match',
    'lender.check_line_unreachable' => 'Lender could not be reached',
    'lender.check_notice_confirmed' => '{lender}: confirmed with the lender',
    'lender.check_notice_not_confirmed' => '{lender}: Not Confirmed',
    'lender.check_notice_does_not_match' => '{lender}: the lender says this does not match',
    'lender.check_notice_unreachable' => '{lender}: Lender could not be reached',
    'lender.check_withdraw' => 'Withdraw this check',
    'lender.check_option_withdrawn' => 'Withdrawn',
    'lender.check_notice_withdrawn' => '{lender}: the check with the lender was withdrawn',
    'lender.request_button' => 'Request this lender',
    'lender.request_heading' => 'Request a lender',
    'lender.request_note' => 'Note',
    'lender.request_send' => 'Send request',
    'lender.request_sent' => 'Request sent. You will be notified with the response',
    'lender.request_waiting' => 'This lender has already been contacted waiting for response',
    'lender.request_added' => '{lender} has been added. You can now select it',
    'lender.request_merged' => '{lender} is in the list as {existing}. You can now select it',
    'lender.request_rejected' => '{lender} was not added: {reason}',
    'lender.request_use_existing' => 'Use existing lender',
    'lender.request_reject' => 'Reject',
    'lender.request_reason' => 'Reason',
    'lender.request_empty' => 'No requests yet',
];

test('every approved key has exactly the approved wording and nothing else is in the file', function () {
    $file = json_decode(file_get_contents(resource_path('wording.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($file)->toBe(APPROVED_WORDING);
});

test('the reader gives the wording and fills the placeholders in one pass', function () {
    expect(Wording::get('repayment.date'))->toBe('Repayment Date')
        ->and(Wording::get('repayment.sms', ['amount' => '250.00', 'lender' => 'Asante {code}', 'code' => '483920']))
        ->toBe('Your agent recorded a repayment of GHS 250.00 to Asante {code}. Code: 483920');
});

test('the reader refuses a key with no wording, and resolve leaves other text alone', function () {
    expect(fn() => Wording::get('repayment.nope'))->toThrow(InvalidArgumentException::class)
        ->and(Wording::resolve('repayment.stale'))->toBe('The loan changed, so this repayment was not accepted.')
        ->and(Wording::resolve('Enter an amount above zero.'))->toBe('Enter an amount above zero.');
});

test('every repayment or lender key named in the code has approved wording', function () {
    $audit = ['lender.created', 'lender.updated'];
    $files = [];

    foreach (['app', 'resources/js'] as $dir) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir))) as $file) {
            if (preg_match('/\.(php|tsx?)$/', $file->getFilename()) && ! preg_match('/\.test\.tsx?$/', $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }
    }

    $found = [];

    foreach ($files as $path) {
        preg_match_all('/[\'"`]((?:repayment|lender)\.[a-z_.${}]+)/', file_get_contents($path), $matches);

        foreach ($matches[1] as $literal) {
            $cut = strcspn($literal, '${');
            $prefix = $cut < strlen($literal) ? substr($literal, 0, $cut) : false;
            $known = $prefix !== false
                ? array_filter(array_keys(APPROVED_WORDING), fn($key) => str_starts_with($key, $prefix))
                : array_filter([$literal], fn($key) => array_key_exists($key, APPROVED_WORDING));

            if ($known === [] && ! in_array($literal, $audit, true) && ! in_array($literal, ['lender.required', 'lender.uuid'], true)) {
                $found[] = basename($path) . ': ' . $literal;
            }
        }
    }

    expect($found)->toBe([]);
});

test('in production a missing key gives the plain sentence and logs once, and never throws', function () {
    app()['env'] = 'production';
    Cache::flush();
    Log::spy();

    expect(Wording::get('repayment.nope'))->toBe('Something went wrong. Please try again.')
        ->and(Wording::get('repayment.nope', ['amount' => '1']))->toBe('Something went wrong. Please try again.')
        ->and(Wording::resolve('repayment.nope'))->toBe('Something went wrong. Please try again.')
        ->and(Wording::resolve('Enter an amount above zero.'))->toBe('Enter an amount above zero.');

    Log::shouldHaveReceived('error')->once();
});

test('in local and testing a missing key still throws, from get and from resolve', function () {
    expect(fn() => Wording::get('lender.nope'))->toThrow(InvalidArgumentException::class)
        ->and(fn() => Wording::resolve('lender.nope'))->toThrow(InvalidArgumentException::class);

    app()['env'] = 'local';

    expect(fn() => Wording::get('lender.nope'))->toThrow(InvalidArgumentException::class);
});
