<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierProfileRequest;
use App\Http\Requests\Supplier\VerifySupplierEmailRequest;
use App\Models\Supplier;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplierProfileController extends Controller
{
    private const OTP_TYPE = 'supplier_email_verification';

    public function __construct(private readonly OtpService $otpService) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Supplier/Profile/Create', [
            'supplier' => $this->present($request->user()->supplier),
        ]);
    }

    public function store(StoreSupplierProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->supplier !== null) {
            throw ValidationException::withMessages([
                'email' => 'You already have a marketplace profile.',
            ]);
        }

        $data = $request->validated();

        $supplier = Supplier::create([
            'user_id' => $user->id,
            'business_name' => $data['business_name'] ?? null,
            'business_registration_number' => $data['business_registration_number'] ?? null,
            'email' => $data['email'],
        ]);

        $this->otpService->generate($supplier->email, self::OTP_TYPE, 'email');

        return back()->with('success', 'A verification code has been sent to your email.');
    }

    public function verifyEmail(VerifySupplierEmailRequest $request): RedirectResponse
    {
        $supplier = $request->user()->supplier;

        if ($supplier === null || ! $this->otpService->verify($supplier->email, $request->validated()['code'], self::OTP_TYPE)) {
            throw ValidationException::withMessages([
                'code' => 'That code is not right or has expired.',
            ]);
        }

        $supplier->update(['email_verified_at' => now()]);

        return back()->with('success', 'Your email is verified.');
    }

    private function present(?Supplier $supplier): ?array
    {
        if ($supplier === null) {
            return null;
        }

        return [
            'business_name' => $supplier->business_name,
            'business_registration_number' => $supplier->business_registration_number,
            'email' => $supplier->email,
            'email_verified' => $supplier->isEmailVerified(),
            'verification_status' => $supplier->verificationStatus(),
        ];
    }
}
