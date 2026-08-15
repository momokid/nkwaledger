<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FarmerGroupType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FarmerGroupTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            FarmerGroupType::query()->orderBy('name')->get(['id', 'name'])
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:farmer_group_types,name'],
        ]);

        FarmerGroupType::create($validated);

        return back()->with('success', 'Farmer group type created.');
    }

    public function update(Request $request, FarmerGroupType $farmerGroupType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('farmer_group_types', 'name')->ignore($farmerGroupType->id)],
        ]);

        $farmerGroupType->update($validated);

        return back()->with('success', 'Farmer group type updated.');
    }

    public function destroy(FarmerGroupType $farmerGroupType): RedirectResponse
    {
        $farmerGroupType->delete();

        return back()->with('success', 'Farmer group type deleted.');
    }
}
