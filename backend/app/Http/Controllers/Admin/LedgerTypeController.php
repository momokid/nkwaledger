<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLedgerTypeRequest;
use App\Http\Requests\Admin\UpdateLedgerTypeRequest;
use App\Models\LedgerType;
use App\Services\AccessControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerTypeController extends Controller
{
    public function __construct(private AccessControlService $access) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/LedgerTypes/Index', [
            'ledgerTypes' => LedgerType::query()
                ->orderBy('name')
                ->paginate(15),
            'permissions' => [
                'create' => $this->access->can($user, 'ledger-accounts.create'),
                'update' => $this->access->can($user, 'ledger-accounts.update'),
                'delete' => $this->access->can($user, 'ledger-accounts.delete'),
            ],
        ]);
    }

    public function store(StoreLedgerTypeRequest $request): RedirectResponse
    {
        LedgerType::create($request->validated());

        return back()->with('success', 'Ledger type created.');
    }

    public function update(UpdateLedgerTypeRequest $request, LedgerType $ledgerType): RedirectResponse
    {
        $ledgerType->update($request->validated());

        return back()->with('success', 'Ledger type updated.');
    }

    public function destroy(LedgerType $ledgerType): RedirectResponse
    {
        $ledgerType->delete();

        return back()->with('success', 'Ledger type deleted.');
    }
}
