<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLedgerCategoryRequest;
use App\Http\Requests\Admin\UpdateLedgerCategoryRequest;
use App\Models\LedgerCategory;
use App\Models\LedgerFundamentalType;
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
                ->with('fundamentalType')
                ->orderBy('name')
                ->paginate(15),
            // populates the Fundamental Type <select> on the create/edit form — always all five, since this list is fixed
            'fundamentalTypes' => LedgerFundamentalType::orderBy('name')->get(['id', 'name']),
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
