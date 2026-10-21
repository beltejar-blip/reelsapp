<?php

namespace App\Http\Responses;

use App\Actions\Auth\IssueLoginOtp;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OtpRegisterResponse implements RegisterResponse
{
    public function __construct(private IssueLoginOtp $issueLoginOtp) {}

    public function toResponse($request): Response
    {
        $guard = Auth::guard(config('fortify.guard'));
        $user = $guard->user();

        if (! $user instanceof User) {
            throw new LogicException('The registration OTP challenge requires an authenticated user.');
        }

        try {
            $this->issueLoginOtp->issue($user, $request, $request->boolean('remember'));
        } catch (Throwable $exception) {
            $guard->logout();

            throw $exception;
        }

        $guard->logout();

        return $request->wantsJson()
            ? response()->json(['otp_required' => true], 202)
            : redirect()->route('email-otp.challenge');
    }
}
