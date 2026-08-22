<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ActivateRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ActivationController extends Controller
{
    private const TYPE = 'invitation';

    public function __construct(private readonly OtpService $otpService) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Activate');
    }

    public function store(ActivateRequest $request): RedirectResponse
    {
        $phone = $request->validated()['phone'];

        // only an account still waiting to be activated has anything to claim
        $pending = User::where('phone', $phone)->whereNull('password')->first();

        if ($pending && ! $this->otpService->hasLiveCode($phone, self::TYPE)) {
            // the invitation code has run out, so this person needs a new one
            $this->otpService->generate($phone, self::TYPE);
        }

        // the reply looks the same whether or not there was anything to claim
        $request->session()->put('auth.login_identifier', $phone);
        $request->session()->put('auth.otp_type', self::TYPE);

        return redirect('/verify-otp');
    }
}
