<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFarmTypeCategoryRequest;
use App\Http\Requests\UpdateFarmTypeCategoryRequest;
use App\Models\FarmTypeCategory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\AccessControlService;
use Illuminate\Http\Request;

class FarmTypeCategoryController extends Controller
{
    public function __construct(private AccessControlService $access) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/FarmTypeCategories/Index', [
            'categories' => FarmTypeCategory::query()
                ->orderBy('name')
                ->paginate(15),
            // lets the frontend hide controls the current user isn't allowed to use, rather than showing a button that would just 403 on submit
            'permissions' => [
                'create' => $this->access->can($user, 'farm-type-categories.create'),
                'update' => $this->access->can($user, 'farm-type-categories.update'),
                'delete' => $this->access->can($user, 'farm-type-categories.delete'),
            ],
        ]);
    }

    public function store(StoreFarmTypeCategoryRequest $request): RedirectResponse
    {
        FarmTypeCategory::create($request->validated());

        return back()->with('success', 'Category created.');
    }

    public function update(UpdateFarmTypeCategoryRequest $request, FarmTypeCategory $farmTypeCategory): RedirectResponse
    {
        $farmTypeCategory->update($request->validated());

        return back()->with('success', 'Category updated.');
    }

    public function destroy(FarmTypeCategory $farmTypeCategory): RedirectResponse
    {
        $farmTypeCategory->delete();

        return back()->with('success', 'Category deleted.');
    }
}
