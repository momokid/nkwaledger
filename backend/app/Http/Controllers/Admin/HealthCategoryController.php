<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHealthCategoryRequest;
use App\Http\Requests\Admin\UpdateHealthCategoryRequest;
use App\Models\FarmTypeCategory;
use App\Models\HealthCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HealthCategoryController extends Controller
{
    public function index(): Response
    {
        $groups = FarmTypeCategory::query()
            ->with(['healthCategories' => fn($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('id')
            ->get()
            ->map(fn(FarmTypeCategory $farm) => [
                'name' => $farm->name,
                'categories' => $farm->healthCategories->map(fn(HealthCategory $c) => [
                    'uuid' => $c->uuid,
                    'name' => $c->name,
                    'is_active' => $c->is_active,
                ])->values(),
            ]);

        return Inertia::render('Admin/HealthCategories/Index', ['groups' => $groups]);
    }

    public function store(StoreHealthCategoryRequest $request): RedirectResponse
    {
        $farm = FarmTypeCategory::where('name', $request->validated('farm_category'))->firstOrFail();

        $farm->healthCategories()->create([
            'name' => $request->validated('name'),
            'sort_order' => ($farm->healthCategories()->max('sort_order') ?? 0) + 1,
        ]);

        return back()->with('success', 'Saved.');
    }

    public function update(UpdateHealthCategoryRequest $request, HealthCategory $healthCategory): RedirectResponse
    {
        $healthCategory->update($request->validated());

        return back()->with('success', 'Saved.');
    }

    public function move(Request $request, HealthCategory $healthCategory): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $siblings = HealthCategory::where('farm_type_category_id', $healthCategory->farm_type_category_id)
            ->orderBy('sort_order')->orderBy('id')->get();

        $from = $siblings->search(fn($c) => $c->is($healthCategory));
        $to = $direction === 'up' ? $from - 1 : $from + 1;

        if ($to >= 0 && $to < $siblings->count()) {
            $ids = $siblings->pluck('id')->all();
            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];

            // renumber the whole list so tied sort orders cannot make a move do nothing
            foreach ($ids as $position => $id) {
                HealthCategory::whereKey($id)->update(['sort_order' => $position + 1]);
            }
        }

        return back()->with('success', 'Saved.');
    }
}
