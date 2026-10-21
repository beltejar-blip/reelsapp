<?php

use App\Mail\LoginOtp;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    Mail::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('email-otp.challenge'));

    $this->assertGuest();
    $response->assertSessionHas('login_otp.user_id');

    Mail::assertSent(LoginOtp::class, fn (LoginOtp $mail): bool => $mail->hasTo('test@example.com'));
});
