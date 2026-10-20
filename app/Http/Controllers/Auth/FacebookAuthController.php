<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

class FacebookAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (
            blank(config('services.facebook.client_id'))
            || blank(config('services.facebook.client_secret'))
            || blank(config('services.facebook.redirect'))
        ) {
            return redirect()->route('login')->withErrors([
                'email' => __('Facebook login is not configured.'),
            ]);
        }

        return Socialite::driver('facebook')
            ->scopes(['email'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->query('error')) {
            return redirect()->route('login')->withErrors([
                'email' => __('Facebook login was canceled. Please try again or log in with your email and password.'),
            ]);
        }

        $facebookUser = Socialite::driver('facebook')->user();
        $email = Str::lower((string) $facebookUser->getEmail());

        if (blank($email)) {
            return redirect()->route('login')->withErrors([
                'email' => __('Facebook did not provide an email address. Please log in with your email and password.'),
            ]);
        }

        $user = User::query()
            ->where('facebook_id', $facebookUser->getId())
            ->first();

        if ($user === null) {
            if (User::query()->where('email', $email)->exists()) {
                return redirect()->route('login')->withErrors([
                    'email' => __('An account with this email already exists. Log in with your email and password instead.'),
                ]);
            }

            $user = User::create([
                'name' => $facebookUser->getName() ?: $email,
                'email' => $email,
                'password' => Str::random(40),
            ]);

            $user->forceFill([
                'facebook_id' => $facebookUser->getId(),
                'email_verified_at' => now(),
            ])->save();
        }

        if (
            Features::enabled(Features::twoFactorAuthentication())
            && $user->two_factor_secret
            && (! Fortify::confirmsTwoFactorAuthentication() || ! is_null($user->two_factor_confirmed_at))
        ) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        $request->session()->forget(['login.id', 'login.remember']);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
