<?php

namespace App\Http\Controllers\Ussd;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\PasswordConfirmationService;
use App\Services\Ledger\TransactionService;
use Illuminate\Http\Request;

class UssdTransactionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'phone_number' => ['required'],
            'amount' => ['required', 'numeric'],
            'category' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('phone_number', $request->phone_number)->firstOrFail();

        // Password confirmation
        app(PasswordConfirmationService::class)
            ->confirm($user, $request->password);

        // Save transaction
        app(TransactionService::class)->record(
            user: $user,
            type: 'income',
            category: $request->category,
            amount: $request->amount,
            source: 'ussd'
        );

        return response()->json([
            'message' => 'Transaction saved successfully.',
        ]);
    }
}
