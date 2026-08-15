<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'surname'    => $validated['surname'],
                'first_name' => $validated['first_name'],
                'other_name' => $validated['other_name'] ?? null,
                'phone'      => $validated['phone'],
                'email'      => $validated['email'] ?? null,
                'password'   => Hash::make($validated['password']),
            ]);

            $user->assignRole('farmer');

            $this->otpService->generate($user->phone, 'registration');

            $request->session()->put('auth.login_identifier', $user->phone);
            $request->session()->put('auth.otp_type', 'registration');
        });

        return redirect('/verify-otp');
    }
}
