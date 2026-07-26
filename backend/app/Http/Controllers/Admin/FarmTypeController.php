<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFarmTypeRequest;
use App\Http\Requests\UpdateFarmTypeRequest;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Services\AccessControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FarmTypeController extends Controller
{
    public function __construct(private AccessControlService $access) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/FarmTypes/Index', [
            'farmTypes' => FarmType::query()
                ->with('category')
                ->orderBy('name')
                ->paginate(15),
            // populates the category <select> on the create/edit form — only active categories, since a
            // deactivated one shouldn't be assignable to new farm types even though existing references stay intact
            'categories' => FarmTypeCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'permissions' => [
                'create' => $this->access->can($user, 'farm-types.create'),
                'update' => $this->access->can($user, 'farm-types.update'),
                'delete' => $this->access->can($user, 'farm-types.delete'),
            ],
        ]);
    }

    public function store(StoreFarmTypeRequest $request): RedirectResponse
    {
        FarmType::create($request->validated());

        return back()->with('success', 'Farm type created.');
    }

    public function update(UpdateFarmTypeRequest $request, FarmType $farmType): RedirectResponse
    {
        $farmType->update($request->validated());

        return back()->with('success', 'Farm type updated.');
    }

    public function destroy(FarmType $farmType): RedirectResponse
    {
        $farmType->delete();

        return back()->with('success', 'Farm type deleted.');
    }
}
