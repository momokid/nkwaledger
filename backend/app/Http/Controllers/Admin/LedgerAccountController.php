<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLedgerAccountRequest;
use App\Http\Requests\Admin\UpdateLedgerAccountRequest;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Services\AccessControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerAccountController extends Controller
{
    public function __construct(private AccessControlService $access) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/LedgerAccounts/Index', [
            // the nested subcategory chain feeds the derived Dr/Cr shown in the table
            'ledgerAccounts' => LedgerAccount::query()
                ->with(['control', 'type', 'subcategory.category.class'])
                ->orderBy('name')
                ->paginate(15),
            'controls' => LedgerControl::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'subcategories' => LedgerSubcategory::query()
                ->with('category:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'category_id']),
            'types' => LedgerType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            // needed by the subcategory quick-add, which must attach the new record to a parent
            'categories' => LedgerCategory::orderBy('name')->get(['id', 'name']),
            'permissions' => [
                'create' => $this->access->can($user, 'ledger-accounts.create'),
                'update' => $this->access->can($user, 'ledger-accounts.update'),
                'delete' => $this->access->can($user, 'ledger-accounts.delete'),
            ],
        ]);
    }

    public function store(StoreLedgerAccountRequest $request): RedirectResponse
    {
        LedgerAccount::create($request->validated());

        return back()->with('success', 'Ledger account created.');
    }

    public function update(UpdateLedgerAccountRequest $request, LedgerAccount $ledgerAccount): RedirectResponse
    {
        $ledgerAccount->update($request->validated());

        return back()->with('success', 'Ledger account updated.');
    }

    public function destroy(LedgerAccount $ledgerAccount): RedirectResponse
    {
        // caught here so the admin sees a plain message rather than the model's exception
        if ($ledgerAccount->is_system) {
            return back()->with('error', 'This is a system account and cannot be deleted.');
        }

        $ledgerAccount->delete();

        return back()->with('success', 'Ledger account deleted.');
    }
}
