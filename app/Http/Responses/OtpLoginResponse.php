<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse;
use Symfony\Component\HttpFoundation\Response;

class OtpLoginResponse implements LoginResponse
{
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? response()->json(['otp_required' => true], 202)
            : redirect()->route('email-otp.challenge');
    }
}
