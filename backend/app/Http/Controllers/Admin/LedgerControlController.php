<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLedgerControlRequest;
use App\Http\Requests\Admin\UpdateLedgerControlRequest;
use App\Models\LedgerControl;
use App\Services\AccessControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerControlController extends Controller
{
    public function __construct(private AccessControlService $access) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/LedgerControls/Index', [
            'ledgerControls' => LedgerControl::query()
                ->orderBy('name')
                ->paginate(15),
            'permissions' => [
                'create' => $this->access->can($user, 'ledger-accounts.create'),
                'update' => $this->access->can($user, 'ledger-accounts.update'),
                'delete' => $this->access->can($user, 'ledger-accounts.delete'),
            ],
        ]);
    }

    public function store(StoreLedgerControlRequest $request): RedirectResponse
    {
        LedgerControl::create($request->validated());

        return back()->with('success', 'Ledger control created.');
    }

    public function update(UpdateLedgerControlRequest $request, LedgerControl $ledgerControl): RedirectResponse
    {
        $ledgerControl->update($request->validated());

        return back()->with('success', 'Ledger control updated.');
    }

    public function destroy(LedgerControl $ledgerControl): RedirectResponse
    {
        $ledgerControl->delete();

        return back()->with('success', 'Ledger control deleted.');
    }
}
