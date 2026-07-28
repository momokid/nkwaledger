<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLedgerSubcategoryRequest;
use App\Http\Requests\Admin\UpdateLedgerSubcategoryRequest;
use App\Models\LedgerCategory;
use App\Models\LedgerSubcategory;
use App\Services\AccessControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerSubcategoryController extends Controller
{
    public function __construct(private AccessControlService $access) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/LedgerSubcategories/Index', [
            'ledgerSubcategories' => LedgerSubcategory::query()
                ->with('category')
                ->orderBy('name')
                ->paginate(15),
            // populates the parent Category <select> on the create/edit form
            'categories' => LedgerCategory::orderBy('name')->get(['id', 'name']),
            'permissions' => [
                'create' => $this->access->can($user, 'ledger-accounts.create'),
                'update' => $this->access->can($user, 'ledger-accounts.update'),
                'delete' => $this->access->can($user, 'ledger-accounts.delete'),
            ],
        ]);
    }

    public function store(StoreLedgerSubcategoryRequest $request): RedirectResponse
    {
        LedgerSubcategory::create($request->validated());

        return back()->with('success', 'Ledger subcategory created.');
    }

    public function update(UpdateLedgerSubcategoryRequest $request, LedgerSubcategory $ledgerSubcategory): RedirectResponse
    {
        $ledgerSubcategory->update($request->validated());

        return back()->with('success', 'Ledger subcategory updated.');
    }

    public function destroy(LedgerSubcategory $ledgerSubcategory): RedirectResponse
    {
        $ledgerSubcategory->delete();

        return back()->with('success', 'Ledger subcategory deleted.');
    }
}