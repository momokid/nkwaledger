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
use App\Services\AuditService;

class LoginRequest extends FormRequest
{
    use NormalisesPhone;

    // a real bcrypt hash of a value nobody will guess, used only to keep the timing even
    private const DECOY_HASH = '$2y$12$LqPZzTMS3Y7hfLLQhaPjfeu5uyv4dQjBRuUYbLNpUQxFmMTXLpO0y';

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

        // hashing runs either way, so an unknown number takes as long as a known one
        $known = $user && $user->password !== null;
        $hash  = $known ? $user->password : self::DECOY_HASH;

        $matched = Hash::check($this->input('password'), $hash);

        if (! $known || ! $matched) {
            RateLimiter::hit($this->throttleKey());
            // counted separately, since trying many accounts once each never trips the per-account limit
            RateLimiter::hit($this->ipThrottleKey(), 3600);

            // the number tried is the useful part; the password never goes near the log
            app(AuditService::class)->record('login.failed', [
                'identifier' => $this->input('identifier'),
            ]);

            throw ValidationException::withMessages([
                'identifier' => trans('auth.failed'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'identifier' => 'Your account has been deactivated. Contact support.',
            ]);
        }

        // only the account's own counter clears; the ip counter stands, or one good password would wipe it
        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    public function ensureIsNotRateLimited(): void
    {
        $key = match (true) {
            RateLimiter::tooManyAttempts($this->throttleKey(), 5)   => $this->throttleKey(),
            RateLimiter::tooManyAttempts($this->ipThrottleKey(), 20) => $this->ipThrottleKey(),
            default                                                  => null,
        };

        if ($key === null) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($key);

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

    // one bucket for the whole machine, whatever account it is reaching for
    public function ipThrottleKey(): string
    {
        return 'login-ip|' . $this->ip();
    }
}
