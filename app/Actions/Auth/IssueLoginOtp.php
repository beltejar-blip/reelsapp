<?php

namespace App\Actions\Auth;

use App\Mail\LoginOtp;
use App\Models\LoginEmailOtp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class IssueLoginOtp
{
    public function issue(User $user, Request $request, bool $remember): void
    {
        $rateLimitKey = 'login-otp:'.$user->getKey().'|'.$request->ip();
        $hourlyRateLimitKey = $rateLimitKey.':hourly';

        if (
            RateLimiter::tooManyAttempts($rateLimitKey, 3)
            || RateLimiter::tooManyAttempts($hourlyRateLimitKey, 10)
        ) {
            throw ValidationException::withMessages([
                'email' => __('Too many login codes were requested. Please wait before trying again.'),
            ]);
        }

        RateLimiter::hit($rateLimitKey, 60);
        RateLimiter::hit($hourlyRateLimitKey, 3600);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $sessionToken = Str::random(64);

        $otp = LoginEmailOtp::updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'session_token_hash' => hash('sha256', $sessionToken),
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
                'attempts' => 0,
                'remember' => $remember,
            ],
        );

        try {
            Mail::to($user)->send(new LoginOtp($code));
        } catch (Throwable $exception) {
            $otp->delete();
            $request->session()->forget('login_otp');

            throw $exception;
        }

        $request->session()->put('login_otp', [
            'user_id' => $user->getKey(),
            'token' => $sessionToken,
        ]);
    }
}
