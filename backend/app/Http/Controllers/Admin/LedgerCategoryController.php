<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLedgerCategoryRequest;
use App\Http\Requests\Admin\UpdateLedgerCategoryRequest;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Services\AccessControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerCategoryController extends Controller
{
    public function __construct(private AccessControlService $access) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/LedgerCategories/Index', [
            'ledgerCategories' => LedgerCategory::query()
                ->with('class')
                ->orderBy('name')
                ->paginate(15),
            // populates the Class <select> on the create/edit form — only active classes, since a
            // deactivated one shouldn't be assignable to new categories even though existing references stay intact
            'classes' => LedgerClass::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'permissions' => [
                'create' => $this->access->can($user, 'ledger-accounts.create'),
                'update' => $this->access->can($user, 'ledger-accounts.update'),
                'delete' => $this->access->can($user, 'ledger-accounts.delete'),
            ],
        ]);
    }

    public function store(StoreLedgerCategoryRequest $request): RedirectResponse
    {
        LedgerCategory::create($request->validated());

        return back()->with('success', 'Ledger category created.');
    }

    public function update(UpdateLedgerCategoryRequest $request, LedgerCategory $ledgerCategory): RedirectResponse
    {
        $ledgerCategory->update($request->validated());

        return back()->with('success', 'Ledger category updated.');
    }

    public function destroy(LedgerCategory $ledgerCategory): RedirectResponse
    {
        $ledgerCategory->delete();

        return back()->with('success', 'Ledger category deleted.');
    }
}
