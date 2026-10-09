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

        // an email-channel invite's code lives under the email, not the phone - default
        // to the phone/sms behaviour and only switch when a live email code says otherwise
        $identifier = $phone;
        $channel = 'sms';

        if ($pending && $this->otpService->hasLiveCode($phone, self::TYPE)) {
            // a live code already sits under the phone - today's behaviour, unchanged
        } elseif ($pending && $pending->email && $this->otpService->hasLiveCode($pending->email, self::TYPE)) {
            $identifier = $pending->email;
            $channel = 'email';
        } elseif ($pending) {
            // neither identifier has a live code - expired, used, or never sent
            $this->otpService->generate($phone, self::TYPE);
        }

        // the reply looks the same whether or not there was anything to claim
        $request->session()->put('auth.login_identifier', $identifier);
        $request->session()->put('auth.otp_type', self::TYPE);
        $request->session()->put('auth.otp_channel', $channel);

        return redirect('/verify-otp');
    }
}
