<?php

use App\Models\FarmTypeCategory;
use App\Models\LedgerAccount;
use App\Models\TransactionTemplate;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\TransactionTemplateSeeder;

beforeEach(function () {
    foreach (['Aquatic', 'Crop', 'Livestock'] as $name) {
        FarmTypeCategory::firstOrCreate(['name' => $name]);
    }

    $this->seed(LedgerAccountSeeder::class);
    $this->seed(TransactionTemplateSeeder::class);
});

// fish are not goats, so a bank sees them apart
it('seeds an account for fish stock', function () {
    expect(LedgerAccount::where('name', 'Fish Stock A/C')->exists())->toBeTrue();
});

it('seeds the everyday templates', function () {
    foreach (['produce_sale', 'other_income', 'labour_cost', 'transport_cost', 'input_purchase'] as $slug) {
        $this->assertDatabaseHas('transaction_templates', ['slug' => $slug]);
    }
});

// something true on every farm belongs to no kind of farming
it('leaves the everyday templates without a category', function () {
    $everyday = TransactionTemplate::whereIn('slug', [
        'produce_sale',
        'other_income',
        'labour_cost',
        'transport_cost',
        'input_purchase',
    ])->get();

    expect($everyday)->toHaveCount(5);
    expect($everyday->whereNotNull('farm_type_category_id'))->toBeEmpty();
});

it('seeds the livestock templates under livestock', function () {
    $livestock = FarmTypeCategory::where('name', 'Livestock')->first();

    foreach (['animal_purchase', 'feed_purchase', 'vet_cost', 'animal_sale', 'produce_of_animal_sale', 'animal_loss'] as $slug) {
        $template = TransactionTemplate::where('slug', $slug)->first();

        expect($template)->not->toBeNull();
        expect($template->farm_type_category_id)->toBe($livestock->id);
    }
});

it('seeds the crop templates under crop', function () {
    $crop = FarmTypeCategory::where('name', 'Crop')->first();

    foreach (['seed_purchase', 'fertiliser_purchase', 'crop_loss'] as $slug) {
        expect(TransactionTemplate::where('slug', $slug)->first()?->farm_type_category_id)->toBe($crop->id);
    }
});

it('seeds the fish templates under aquatic', function () {
    $aquatic = FarmTypeCategory::where('name', 'Aquatic')->first();

    foreach (['fingerling_purchase', 'fish_feed_purchase', 'fish_sale', 'fish_loss'] as $slug) {
        expect(TransactionTemplate::where('slug', $slug)->first()?->farm_type_category_id)->toBe($aquatic->id);
    }
});

// buying stock is something owned, not money spent
it('treats buying animals as an asset', function () {
    $template = TransactionTemplate::where('slug', 'animal_purchase')->first();

    expect($template->transaction_type)->toBe('EXPENSE');
    expect($template->debitAccount->name)->toBe('Livestock A/C');
    expect($template->creditAccount->name)->toBe('Cash A/C');
});

it('treats buying fingerlings as an asset', function () {
    expect(TransactionTemplate::where('slug', 'fingerling_purchase')->first()->debitAccount->name)
        ->toBe('Fish Stock A/C');
});

// value gone is not money paid
it('records a death as a loss', function () {
    foreach (['animal_loss', 'crop_loss', 'fish_loss'] as $slug) {
        $template = TransactionTemplate::where('slug', $slug)->first();

        expect($template->transaction_type)->toBe('LOSS');
        // nothing is paid or received, so no side is replaced
        expect($template->settlement_side)->toBe('none');
    }
});

it('writes a loss off against what was owned', function () {
    expect(TransactionTemplate::where('slug', 'animal_loss')->first()->creditAccount->name)
        ->toBe('Livestock A/C');
    expect(TransactionTemplate::where('slug', 'fish_loss')->first()->creditAccount->name)
        ->toBe('Fish Stock A/C');
});

// anything against a pen or plot has to say which one
it('asks which part of the farm where it matters', function () {
    foreach (['feed_purchase', 'animal_loss', 'fish_sale', 'crop_loss'] as $slug) {
        expect(TransactionTemplate::where('slug', $slug)->first()->requires_farm_unit)->toBeTrue();
    }
});

it('does not ask which part of the farm for everyday things', function () {
    foreach (['labour_cost', 'transport_cost', 'other_income'] as $slug) {
        expect(TransactionTemplate::where('slug', $slug)->first()->requires_farm_unit)->toBeFalse();
    }
});

// money in lands where the farmer says, money out comes from where they say
it('replaces the right side on income and expenses', function () {
    expect(TransactionTemplate::where('slug', 'animal_sale')->first()->settlement_side)->toBe('debit');
    expect(TransactionTemplate::where('slug', 'feed_purchase')->first()->settlement_side)->toBe('credit');
});

it('says every sentence in plain words', function () {
    expect(TransactionTemplate::where('slug', 'produce_sale')->first()->name)
        ->toBe('I sold my farm produce');
    expect(TransactionTemplate::where('slug', 'vet_cost')->first()->name)
        ->toBe('I bought medicine or a vet');
    expect(TransactionTemplate::where('slug', 'crop_loss')->first()->name)
        ->toBe('I lost all my farm produce');
});

it('can run again without doubling anything', function () {
    $before = TransactionTemplate::count();

    $this->seed(TransactionTemplateSeeder::class);

    expect(TransactionTemplate::count())->toBe($before);
});

// a category that does not exist yet must not stop the deploy
it('skips a template whose category is missing', function () {
    FarmTypeCategory::where('name', 'Aquatic')->delete();
    TransactionTemplate::where('slug', 'fish_sale')->forceDelete();

    $this->seed(TransactionTemplateSeeder::class);

    expect(TransactionTemplate::where('slug', 'fish_sale')->exists())->toBeFalse();
    expect(TransactionTemplate::where('slug', 'produce_sale')->exists())->toBeTrue();
});
