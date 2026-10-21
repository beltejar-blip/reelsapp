<?php

use App\Mail\LoginOtp;
use App\Models\LoginEmailOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('the login email contains the one-time code', function () {
    $mail = new LoginOtp('123456');

    $mail->assertSeeInHtml('123456');
    $mail->assertSeeInText('123456');
});

test('users can authenticate after verifying the emailed code', function () {
    Mail::fake();
    $user = User::factory()->unverified()->create();

    $loginResponse = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ]);

    $loginResponse->assertRedirect(route('email-otp.challenge'));
    $this->assertGuest();
    $this->get(route('reels.index'))->assertRedirect(route('login'));
    $this->get(route('email-otp.challenge'))->assertOk()->assertSee($user->email);

    $code = null;
    Mail::assertSent(LoginOtp::class, function (LoginOtp $mail) use ($user, &$code): bool {
        $code = $mail->code;

        return $mail->hasTo($user->email);
    });

    $otp = LoginEmailOtp::query()->where('user_id', $user->id)->firstOrFail();
    expect(Hash::check($code, $otp->code_hash))->toBeTrue();

    $response = $this->post(route('email-otp.verify'), ['code' => $code]);

    $response->assertRedirect(route('reels.index'));
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseMissing('login_email_otps', ['user_id' => $user->id]);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email_verified_at' => now(),
    ]);
});

test('incorrect email codes do not authenticate the user', function () {
    Mail::fake();
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $code = null;
    Mail::assertSent(LoginOtp::class, function (LoginOtp $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });
    $wrongCode = $code === '000000' ? '111111' : '000000';

    $response = $this->post(route('email-otp.verify'), ['code' => $wrongCode]);

    $response->assertSessionHasErrors('code');
    $this->assertGuest();
    $this->assertDatabaseHas('login_email_otps', ['user_id' => $user->id]);
});

test('a code can only be used five times before a new login is required', function () {
    Mail::fake();
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $code = null;
    Mail::assertSent(LoginOtp::class, function (LoginOtp $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });
    $wrongCode = $code === '000000' ? '111111' : '000000';

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $response = $this->post(route('email-otp.verify'), ['code' => $wrongCode]);

        if ($attempt < 5) {
            $response->assertSessionHasErrors('code');
        } else {
            $response->assertRedirect(route('login'));
        }
    }

    $this->assertGuest();
    $this->assertDatabaseMissing('login_email_otps', ['user_id' => $user->id]);
});

test('resending a code invalidates the previous code', function () {
    Mail::fake();
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->post(route('email-otp.resend'))->assertRedirect(route('email-otp.challenge'));

    $codes = [];
    Mail::assertSent(LoginOtp::class, function (LoginOtp $mail) use (&$codes): bool {
        $codes[] = $mail->code;

        return true;
    });
    expect($codes)->toHaveCount(2);
    expect($codes[0])->not->toBe($codes[1]);

    $this->post(route('email-otp.verify'), ['code' => $codes[0]])
        ->assertSessionHasErrors('code');
    $this->assertGuest();

    $this->post(route('email-otp.verify'), ['code' => $codes[1]])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
});

test('expired email codes cannot authenticate the user', function () {
    Mail::fake();
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $code = null;
    Mail::assertSent(LoginOtp::class, function (LoginOtp $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    $this->travelTo(now()->addMinutes(11));
    $response = $this->post(route('email-otp.verify'), ['code' => $code]);

    $response->assertRedirect(route('login'));
    $this->assertGuest();
    $this->assertDatabaseMissing('login_email_otps', ['user_id' => $user->id]);
});

test('email codes cannot be used from a different login session', function () {
    Mail::fake();
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $code = null;
    Mail::assertSent(LoginOtp::class, function (LoginOtp $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    $this->withSession([
        'login_otp' => [
            'user_id' => $user->id,
            'token' => 'different-session-token',
        ],
    ]);
    $response = $this->post(route('email-otp.verify'), ['code' => $code]);

    $response->assertRedirect(route('login'));
    $this->assertGuest();
    $this->assertDatabaseHas('login_email_otps', ['user_id' => $user->id]);
});

test('users must enter a valid six digit code', function () {
    $response = $this->post(route('email-otp.verify'), ['code' => '123']);

    $response->assertSessionHasErrors('code');
    $this->assertGuest();
});
