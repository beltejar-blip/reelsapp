<?php

namespace App\Actions\Fortify;

use App\Actions\Auth\IssueLoginOtp;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LogicException;

class StartLoginOtpChallenge
{
    public function __construct(private IssueLoginOtp $issueLoginOtp) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $guard = Auth::guard(config('fortify.guard'));
        $user = $guard->user();

        if (! $user instanceof User) {
            throw new LogicException('The login OTP challenge requires an authenticated user.');
        }

        try {
            $this->issueLoginOtp->issue($user, $request, $request->boolean('remember'));
        } catch (\Throwable $exception) {
            $guard->logout();

            throw $exception;
        }

        $guard->logout();

        return $next($request);
    }
}
