<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use App\Services\PhoneNormalizer;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'surname'    => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'other_name' => ['nullable', 'string', 'max:100'],
            'phone'      => ['required', 'string', 'max:20', 'unique:users,phone'],
            'email'      => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'   => ['required', 'confirmed', 'min:6'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'surname'    => $validated['surname'],
                'first_name' => $validated['first_name'],
                'other_name' => $validated['other_name'] ?? null,
                'phone' => PhoneNormalizer::normalize($validated['phone']),
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
