<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTransactionTemplateRequest;
use App\Http\Requests\Admin\UpdateTransactionTemplateRequest;
use App\Models\FarmTypeCategory;
use App\Models\LedgerAccount;
use App\Models\TransactionTemplate;
use App\Services\AccessControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionTemplateController extends Controller
{
    public function __construct(private AccessControlService $access) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/TransactionTemplates/Index', [
            'transactionTemplates' => TransactionTemplate::query()
                ->with(['debitAccount:id,name', 'creditAccount:id,name', 'farmTypeCategory:id,name'])
                ->orderBy('transaction_type')
                ->orderBy('name')
                ->paginate(15),
            // only live accounts can back a posting rule, so a deactivated one is never offered
            'ledgerAccounts' => LedgerAccount::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'account_code']),
            'farmTypeCategories' => FarmTypeCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'transactionTypes' => TransactionTemplate::TYPES,
            'settlementSides' => TransactionTemplate::SETTLEMENT_SIDES,
            'permissions' => [
                'create' => $this->access->can($user, 'transaction-templates.create'),
                'update' => $this->access->can($user, 'transaction-templates.update'),
                'delete' => $this->access->can($user, 'transaction-templates.delete'),
            ],
        ]);
    }

    public function store(StoreTransactionTemplateRequest $request): RedirectResponse
    {
        TransactionTemplate::create($request->validated());

        return back()->with('success', 'Transaction template created.');
    }

    public function update(UpdateTransactionTemplateRequest $request, TransactionTemplate $transactionTemplate): RedirectResponse
    {
        if ($transactionTemplate->is_system) {
            return back()->withErrors([
                'name' => 'This is a built in template, so it cannot be edited. Create a new one instead.',
            ]);
        }

        $transactionTemplate->update($request->validated());

        return back()->with('success', 'Transaction template updated.');
    }

    public function destroy(TransactionTemplate $transactionTemplate): RedirectResponse
    {
        if ($transactionTemplate->is_system) {
            return back()->withErrors([
                'name' => 'This is a built in template, so it cannot be removed. Switch it off instead.',
            ]);
        }

        $transactionTemplate->delete();

        return back()->with('success', 'Transaction template deleted.');
    }
}
