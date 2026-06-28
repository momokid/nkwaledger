<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $socialUser   = Socialite::driver($provider)->stateless()->user();
        $existingUser = User::where('email', $socialUser->getEmail())->first();

        if ($existingUser) {
            Auth::login($existingUser);

            if (! $existingUser->phone) {
                return redirect('/complete-profile');
            }

            return $this->redirectByRole($existingUser);
        }

        session([
            'oauth.provider'    => $provider,
            'oauth.provider_id' => $socialUser->getId(),
            'oauth.email'       => $socialUser->getEmail(),
            'oauth.name'        => $socialUser->getName(),
            'oauth.avatar'      => $socialUser->getAvatar(),
            'oauth.token'       => $socialUser->token,
        ]);

        return redirect('/complete-profile');
    }

    private function redirectByRole(User $user): RedirectResponse
    {
        return match (true) {
            $user->hasRole('admin')    => redirect('/admin/dashboard'),
            $user->hasRole('agent')    => redirect('/agent/dashboard'),
            $user->hasRole('farmer')   => redirect('/farmer/dashboard'),
            $user->hasRole('vet')      => redirect('/vet/dashboard'),
            $user->hasRole('adviser')  => redirect('/adviser/dashboard'),
            $user->hasRole('supplier') => redirect('/supplier/dashboard'),
            default                    => redirect('/farmer/dashboard'),
        };
    }
}
