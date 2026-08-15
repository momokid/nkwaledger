<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLedgerClassRequest;
use App\Http\Requests\Admin\UpdateLedgerClassRequest;
use App\Models\LedgerClass;
use App\Services\AccessControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerClassController extends Controller
{
    public function __construct(private AccessControlService $access) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/LedgerClasses/Index', [
            'ledgerClasses' => LedgerClass::query()
                ->orderBy('name')
                ->paginate(15),
            'permissions' => [
                'create' => $this->access->can($user, 'ledger-accounts.create'),
                'update' => $this->access->can($user, 'ledger-accounts.update'),
                'delete' => $this->access->can($user, 'ledger-accounts.delete'),
            ],
        ]);
    }

    public function store(StoreLedgerClassRequest $request): RedirectResponse
    {
        LedgerClass::create($request->validated());

        return back()->with('success', 'Ledger class created.');
    }

    public function update(UpdateLedgerClassRequest $request, LedgerClass $ledgerClass): RedirectResponse
    {
        $ledgerClass->update($request->validated());

        return back()->with('success', 'Ledger class updated.');
    }

    public function destroy(LedgerClass $ledgerClass): RedirectResponse
    {
        $ledgerClass->delete();

        return back()->with('success', 'Ledger class deleted.');
    }
}
