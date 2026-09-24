<?php

namespace App\Http\Controllers\Supplier;

use App\Enums\KioskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\ConfirmKioskRequest;
use App\Http\Requests\Supplier\StoreKioskRequest;
use App\Models\District;
use App\Models\Kiosk;
use App\Models\Region;
use App\Services\KioskCapService;
use App\Services\NotificationService;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KioskController extends Controller
{
    private const OTP_TYPE = 'kiosk_confirmation';

    public function __construct(
        private readonly KioskCapService $caps,
        private readonly OtpService $otpService,
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $supplier = $request->user()->supplier;

        return Inertia::render('Supplier/Kiosks/Index', [
            'kiosks' => $supplier
                ? $supplier->kiosks()->orderByDesc('id')->get()->map(fn(Kiosk $kiosk) => $this->present($kiosk))
                : [],
            'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            'districts' => District::query()->orderBy('name')->get(['id', 'name', 'region_id']),
        ]);
    }

    public function store(StoreKioskRequest $request): RedirectResponse
    {
        $supplier = $request->user()->supplier;

        if ($supplier === null) {
            throw ValidationException::withMessages([
                'name' => 'Finish your marketplace profile before registering a kiosk.',
            ]);
        }

        $requiresApproval = $this->caps->exceedsCap($supplier);

        $kiosk = Kiosk::create([
            ...$request->validated(),
            'supplier_id' => $supplier->id,
            'requires_admin_approval' => $requiresApproval,
        ]);

        $this->otpService->generate($supplier->email, self::OTP_TYPE, 'email');

        if ($requiresApproval) {
            $this->notifications->sendToPermission(
                'marketplace-kiosks.approve',
                'marketplace.kiosk_approval_requested',
                "{$supplier->business_name} registered a 2nd kiosk, \"{$kiosk->name}\", which needs your approval.",
            );
        }

        return back()->with('success', 'A confirmation code has been sent to your email.');
    }

    public function confirm(ConfirmKioskRequest $request, Kiosk $kiosk): RedirectResponse
    {
        $supplier = $request->user()->supplier;

        if ($supplier === null || $kiosk->supplier_id !== $supplier->id) {
            throw ValidationException::withMessages([
                'code' => 'This kiosk does not belong to you.',
            ]);
        }

        if (! $this->otpService->verify($supplier->email, $request->validated()['code'], self::OTP_TYPE)) {
            throw ValidationException::withMessages([
                'code' => 'That code is not right or has expired.',
            ]);
        }

        $kiosk->confirmed_at = now();

        if ($kiosk->isFullyConfirmed()) {
            $kiosk->status = KioskStatus::Active;
        }

        $kiosk->save();

        return back()->with('success', 'Your kiosk is confirmed.');
    }

    private function present(Kiosk $kiosk): array
    {
        return [
            'uuid' => $kiosk->uuid,
            'kiosk_number' => $kiosk->kiosk_number,
            'name' => $kiosk->name,
            'status' => $kiosk->status->value,
            'confirmed_at' => $kiosk->confirmed_at,
            'requires_admin_approval' => $kiosk->requires_admin_approval,
            'admin_approved_at' => $kiosk->admin_approved_at,
            'is_visible' => $kiosk->isVisible(),
        ];
    }
}
