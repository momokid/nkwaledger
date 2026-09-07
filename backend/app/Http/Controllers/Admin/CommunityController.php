<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\District;
use App\Services\Weather\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommunityController extends Controller
{
    public function __construct(
        private readonly WeatherService $weather,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'district_id' => ['required', 'integer', 'exists:districts,id'],
        ]);

        return response()->json(
            Community::query()
                ->where('district_id', $request->integer('district_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'district_id'])
        );
    }

    // a read-only lookup the admin's form calls while typing, so they can see
    // and confirm a suggested pin before anything is actually saved.
    // validated by hand (rather than $request->validate()) so this always
    // answers with JSON, regardless of how the app's exception handler
    // is configured to render a thrown ValidationException.
    public function suggestLocation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $district = District::with('region')->findOrFail($validated['district_id']);

        $query = collect([
            $validated['name'],
            $district->name,
            $district->region?->name,
            'Ghana',
        ])->filter()->implode(', ');

        $result = $this->weather->geocode($query);

        return response()->json([
            'latitude' => $result['latitude'] ?? null,
            'longitude' => $result['longitude'] ?? null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
        ]);

        $validated['name'] = trim($validated['name']);

        $this->assertUniqueWithinDistrict($validated['name'], $validated['district_id']);

        Community::create($validated);

        return back()->with('success', 'Community created.');
    }

    public function update(Request $request, Community $community): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
        ]);

        $validated['name'] = trim($validated['name']);

        $this->assertUniqueWithinDistrict($validated['name'], $validated['district_id'], $community->id);

        $community->update($validated);

        return back()->with('success', 'Community updated.');
    }

    public function destroy(Community $community): RedirectResponse
    {
        $community->delete();

        return back()->with('success', 'Community deleted.');
    }

    private function assertUniqueWithinDistrict(string $name, int $districtId, ?int $ignoreId = null): void
    {
        $exists = Community::query()
            ->where('district_id', $districtId)
            ->where('name', $name)
            ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'A community with this name already exists in the selected district.',
            ]);
        }
    }
}
