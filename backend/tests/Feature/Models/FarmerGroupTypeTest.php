<?php

use App\Models\FarmerGroupType;
use Illuminate\Database\QueryException;

test('a farmer group type can be created', function () {
    $type = FarmerGroupType::create(['name' => 'Cooperative']);

    expect($type->name)->toBe('Cooperative');
});

test('farmer group type name must be unique', function () {
    FarmerGroupType::create(['name' => 'VSLA']);

    expect(fn() => FarmerGroupType::create(['name' => 'VSLA']))
        ->toThrow(QueryException::class);
});

test('a farmer group type can be soft deleted', function () {
    $type = FarmerGroupType::create(['name' => 'Outgrower']);

    $type->delete();

    expect(FarmerGroupType::find($type->id))->toBeNull();
    expect(FarmerGroupType::withTrashed()->find($type->id))->not->toBeNull();
});
