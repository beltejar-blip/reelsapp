<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\IssueLoginOtp;
use App\Http\Controllers\Controller;
use App\Models\LoginEmailOtp;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoginOtpController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $otp = $this->pendingOtp($request);

        if ($otp === null) {
            return redirect()->route('login');
        }

        if ($otp->expires_at->isPast()) {
            $this->clearChallenge($request, $otp);

            return redirect()->route('login')->withErrors([
                'email' => __('Your login code expired. Please sign in again.'),
            ]);
        }

        return view('pages::auth.email-otp', ['user' => $otp->user]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);
        $otp = $this->pendingOtp($request);

        if ($otp === null) {
            $request->session()->forget('login_otp');

            return redirect()->route('login');
        }

        $token = (string) $request->session()->get('login_otp.token');
        $result = DB::transaction(function () use ($otp, $token, $validated): array {
            $currentOtp = LoginEmailOtp::query()
                ->whereKey($otp->getKey())
                ->lockForUpdate()
                ->first();

            if (
                $currentOtp === null
                || ! hash_equals($currentOtp->session_token_hash, hash('sha256', $token))
            ) {
                return ['status' => 'missing'];
            }

            if ($currentOtp->expires_at->isPast()) {
                $currentOtp->delete();

                return ['status' => 'expired'];
            }

            if (! Hash::check($validated['code'], $currentOtp->code_hash)) {
                $currentOtp->increment('attempts');

                if ($currentOtp->attempts >= 5) {
                    $currentOtp->delete();

                    return ['status' => 'locked'];
                }

                return ['status' => 'invalid'];
            }

            $user = $currentOtp->user;
            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $remember = $currentOtp->remember;
            $currentOtp->delete();

            return [
                'status' => 'verified',
                'user' => $user,
                'remember' => $remember,
            ];
        });

        if ($result['status'] === 'invalid') {
            return back()->withErrors([
                'code' => __('That code is incorrect. Please try again.'),
            ]);
        }

        if ($result['status'] !== 'verified') {
            $request->session()->forget('login_otp');

            return redirect()->route('login')->withErrors([
                'email' => $result['status'] === 'locked'
                    ? __('Too many incorrect codes. Please sign in again.')
                    : __('Your login code expired. Please sign in again.'),
            ]);
        }

        Auth::guard(config('fortify.guard'))->login($result['user'], $result['remember']);
        $request->session()->forget('login_otp');
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resend(Request $request, IssueLoginOtp $issueLoginOtp): RedirectResponse
    {
        $otp = $this->pendingOtp($request);

        if ($otp === null || $otp->expires_at->isPast()) {
            if ($otp !== null) {
                $this->clearChallenge($request, $otp);
            } else {
                $request->session()->forget('login_otp');
            }

            return redirect()->route('login')->withErrors([
                'email' => __('Your login code expired. Please sign in again.'),
            ]);
        }

        $issueLoginOtp->issue($otp->user, $request, $otp->remember);

        return redirect()->route('email-otp.challenge')->with('status', __('A new login code has been sent.'));
    }

    private function pendingOtp(Request $request): ?LoginEmailOtp
    {
        $userId = $request->session()->get('login_otp.user_id');
        $token = $request->session()->get('login_otp.token');

        if (! is_numeric($userId) || ! is_string($token) || $token === '') {
            return null;
        }

        $otp = LoginEmailOtp::query()->where('user_id', $userId)->first();

        if ($otp === null || ! hash_equals($otp->session_token_hash, hash('sha256', $token))) {
            return null;
        }

        return $otp;
    }

    private function clearChallenge(Request $request, LoginEmailOtp $otp): void
    {
        $otp->delete();
        $request->session()->forget('login_otp');
    }
}
