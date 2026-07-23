<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class MakeAdminCommand extends Command
{
    protected $signature = 'make:admin';

    protected $description = 'Create an admin user account';

    // added: handle() now receives AccessControlService for the bootstrap safety check
    public function handle(AccessControlService $accessControl): int
    {
        $surname = $this->ask('Surname');
        $firstName = $this->ask('First name');
        $otherName = $this->ask('Other name (optional)');
        $phone = $this->ask('Phone number');
        $email = $this->ask('Email (optional)');
        $password = $this->secret('Password');
        $passwordConfirmation = $this->secret('Confirm password');

        $validator = Validator::make([
            'surname' => $surname,
            'first_name' => $firstName,
            'other_name' => $otherName,
            'phone' => $phone,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'surname' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'other_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $validated = $validator->validated();

        // added: ask whether this admin should manage access, then decide if it must be forced
        $wantsAccessControl = $this->confirm(
            "Should this admin be able to manage other users' roles and permissions?"
        );

        $hasEffectiveHolder = $this->hasEffectiveAccessControlHolder($accessControl);
        $grantAccessControl = $wantsAccessControl || ! $hasEffectiveHolder;

        if (! $wantsAccessControl && ! $hasEffectiveHolder) {
            $this->warn(
                'No admin currently holds access-control.manage, so this account is being granted it automatically to avoid locking everyone out.'
            );
        }

        $user = DB::transaction(function () use ($validated, $grantAccessControl) {
            $user = User::create([
                'surname' => $validated['surname'],
                'first_name' => $validated['first_name'],
                'other_name' => $validated['other_name'] ?: null,
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?: null,
                'password' => Hash::make($validated['password']),
                'is_active' => true,
            ]);

            $user->forceFill([
                'phone_verified_at' => now(),
                'email_verified_at' => ($validated['email'] ?? null) ? now() : null,
            ])->save();

            $user->assignRole('admin');

            // added: grant access-control.manage when requested or forced by the bootstrap check
            if ($grantAccessControl) {
                $user->givePermissionTo('access-control.manage');
            }

            return $user;
        });

        $this->info("Admin account created: {$user->first_name} {$user->surname} ({$user->phone})");

        return self::SUCCESS;
    }

    // added: checks whether anyone can *actually* use access-control.manage right now, accounting for denials
    protected function hasEffectiveAccessControlHolder(AccessControlService $accessControl): bool
    {
        $candidates = User::permission('access-control.manage')->get();

        foreach ($candidates as $candidate) {
            if ($accessControl->can($candidate, 'access-control.manage')) {
                return true;
            }
        }

        return false;
    }
}
