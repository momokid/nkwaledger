<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RegionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Region::query()->orderBy('name')->get(['id', 'name'])
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:regions,name'],
        ]);

        Region::create($validated);

        return back()->with('success', 'Region created.');
    }

    public function update(Request $request, Region $region): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('regions', 'name')->ignore($region->id)],
        ]);

        $region->update($validated);

        return back()->with('success', 'Region updated.');
    }

    public function destroy(Region $region): RedirectResponse
    {
        $region->delete();

        return back()->with('success', 'Region deleted.');
    }
}
