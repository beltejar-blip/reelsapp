<?php

use App\Mail\LoginOtp;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk()->assertDontSee('Continue with Facebook');
});

test('facebook authentication endpoints are not available', function () {
    $this->get('/auth/facebook/redirect')->assertNotFound();
    $this->get('/auth/facebook/callback')->assertNotFound();
});

test('alternate passwordless and two-factor login endpoints are unavailable', function () {
    $this->get('/passkeys/login/options')->assertNotFound();
    $this->get('/two-factor-challenge')->assertNotFound();
});

test('login screen uses https behind a tls-terminating proxy', function () {
    $response = $this->withServerVariables([
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ])->get(route('login'));

    $response
        ->assertOk()
        ->assertSee('https://localhost:8000/build/assets/', false);
});

test('users can authenticate using the login screen', function () {
    Mail::fake();
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('email-otp.challenge'));

    $this->assertGuest();
    $response->assertSessionHas('login_otp.user_id', $user->id);

    Mail::assertSent(LoginOtp::class, fn (LoginOtp $mail): bool => $mail->hasTo($user->email));
});

test('users can not authenticate with invalid password', function () {
    Mail::fake();
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
    Mail::assertNothingSent();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});
