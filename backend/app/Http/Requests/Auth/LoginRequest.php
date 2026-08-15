<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalisesPhone;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    use NormalisesPhone;

    public function authorize(): bool
    {
        return true;
    }

    // an email identifier is left alone; a phone is cleaned so it matches the stored spelling
    protected function prepareForValidation(): void
    {
        if (! filter_var($this->input('identifier'), FILTER_VALIDATE_EMAIL)) {
            $this->normalisePhoneField('identifier');
        }
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string'],
            'password'   => ['required', 'string'],
        ];
    }

    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $identifier = $this->input('identifier');
        $field      = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user       = User::where($field, $identifier)->first();

        if (! $user || ! Hash::check($this->input('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'identifier' => trans('auth.failed'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'identifier' => 'Your account has been deactivated. Contact support.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identifier' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    // reads the normalised identifier, so two spellings of one number cannot buy ten attempts
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('identifier')) . '|' . $this->ip());
    }
}
