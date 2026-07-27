<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerAccountType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LedgerAccountTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            LedgerAccountType::query()->orderBy('name')->get(['id', 'name', 'normal_balance'])
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:ledger_account_types,name'],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
        ]);

        LedgerAccountType::create($validated);

        return back()->with('success', 'Ledger account type created.');
    }

    public function update(Request $request, LedgerAccountType $ledgerAccountType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('ledger_account_types', 'name')->ignore($ledgerAccountType->id)],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
        ]);

        $ledgerAccountType->update($validated);

        return back()->with('success', 'Ledger account type updated.');
    }

    public function destroy(LedgerAccountType $ledgerAccountType): RedirectResponse
    {
        $ledgerAccountType->delete();

        return back()->with('success', 'Ledger account type deleted.');
    }
}
