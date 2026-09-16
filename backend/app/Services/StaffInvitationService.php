<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class StaffInvitationService
{
    // only these can be invited; farmers self-register and admins are never created this way
    public const INVITABLE_ROLES = [
        'agent',
        'vet',
        'adviser',
        'supplier',
    ];

    public function __construct(private readonly OtpService $otpService) {}

    /** @param array{surname:string,first_name:string,other_name:?string,phone:string,email:?string,role:string,channel:string} $details */
    public function invite(array $details): User
    {
        // a failed send must not leave an account nobody can ever activate
        return DB::transaction(function () use ($details) {
            $user = User::create([
                'surname'           => $details['surname'],
                'first_name'        => $details['first_name'],
                'other_name'        => $details['other_name'] ?? null,
                'phone'             => $details['phone'],
                'email'             => $details['email'] ?? null,
                'password'          => null,
            ]);

            $user->assignRole($details['role']);

            $channel = $details['channel'] ?? 'sms';
            $identifier = $channel === 'email' ? $details['email'] : $user->phone;

            $this->otpService->generate($identifier, 'invitation', $channel);

            return $user;
        });
    }
}
