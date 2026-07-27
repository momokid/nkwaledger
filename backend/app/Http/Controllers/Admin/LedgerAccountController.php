<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLedgerAccountRequest;
use App\Http\Requests\UpdateLedgerAccountRequest;
use App\Models\LedgerAccount;
use App\Models\LedgerAccountType;
use App\Services\AccessControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class LedgerAccountController extends Controller
{
    public function __construct(private AccessControlService $access) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/LedgerAccounts/Index', [
            // eager-loads type since normal_balance is a computed accessor that reads $this->type —
            // without this, every row would trigger its own query to resolve normal_balance
            'ledgerAccounts' => LedgerAccount::query()
                ->with('type')
                ->orderBy('name')
                ->paginate(15),
            'types' => LedgerAccountType::query()->orderBy('name')->get(['id', 'name', 'normal_balance']),
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
        try {
            $ledgerAccount->delete();
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }

        return back()->with('success', 'Ledger account deleted.');
    }
}
