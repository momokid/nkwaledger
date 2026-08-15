<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DistrictController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'region_id' => ['required', 'integer', 'exists:regions,id'],
        ]);

        return response()->json(
            District::query()
                ->where('region_id', $request->integer('region_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'region_id'])
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'region_id' => ['required', 'integer', 'exists:regions,id'],
        ]);

        $validated['name'] = trim($validated['name']);

        $this->assertUniqueWithinRegion($validated['name'], $validated['region_id']);

        District::create($validated);

        return back()->with('success', 'District created.');
    }

    public function update(Request $request, District $district): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'region_id' => ['required', 'integer', 'exists:regions,id'],
        ]);

        $validated['name'] = trim($validated['name']);

        $this->assertUniqueWithinRegion($validated['name'], $validated['region_id'], $district->id);

        $district->update($validated);

        return back()->with('success', 'District updated.');
    }

    public function destroy(District $district): RedirectResponse
    {
        $district->delete();

        return back()->with('success', 'District deleted.');
    }

    private function assertUniqueWithinRegion(string $name, int $regionId, ?int $ignoreId = null): void
    {
        $exists = District::query()
            ->where('region_id', $regionId)
            ->where('name', $name)
            ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => 'A district with this name already exists in the selected region.',
            ]);
        }
    }
}
