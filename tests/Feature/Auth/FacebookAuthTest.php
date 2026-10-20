<?php

use App\Models\User;
use Laravel\Fortify\Features;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as FacebookUser;

test('login screen offers Facebook login instead of passkey login', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Continue with Facebook')
        ->assertDontSee('Sign in with a passkey');
});

test('users can start Facebook login when OAuth is configured', function () {
    config([
        'services.facebook.client_id' => 'facebook-client-id',
        'services.facebook.client_secret' => 'facebook-client-secret',
        'services.facebook.redirect' => 'http://localhost/auth/facebook/callback',
    ]);

    Socialite::fake('facebook');

    $this->get(route('auth.facebook.redirect'))
        ->assertRedirect('https://socialite.fake/facebook/authorize');
});

test('Facebook login fails clearly when OAuth is not configured', function () {
    config([
        'services.facebook.client_id' => null,
        'services.facebook.client_secret' => null,
        'services.facebook.redirect' => null,
    ]);

    $this->get(route('auth.facebook.redirect'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'Facebook login is not configured.',
        ]);
});

test('Facebook login creates and authenticates a new user', function () {
    Socialite::fake('facebook', FacebookUser::fake([
        'id' => 'facebook-user-123',
        'name' => 'Facebook User',
        'email' => 'facebook@example.com',
    ]));

    $this->get(route('auth.facebook.callback'))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'facebook@example.com')->firstOrFail();

    expect($user->facebook_id)->toBe('facebook-user-123')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('Facebook login authenticates an existing Facebook account', function () {
    $user = User::factory()->create([
        'facebook_id' => 'facebook-user-existing',
    ]);

    Socialite::fake('facebook', FacebookUser::fake([
        'id' => 'facebook-user-existing',
        'email' => $user->email,
    ]));

    $this->get(route('auth.facebook.callback'))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    expect(User::query()->count())->toBe(1);
});

test('Facebook login requires two-factor authentication when it is enabled', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create([
        'facebook_id' => 'facebook-user-with-two-factor',
    ]);

    Socialite::fake('facebook', FacebookUser::fake([
        'id' => 'facebook-user-with-two-factor',
        'email' => $user->email,
    ]));

    $this->get(route('auth.facebook.callback'))
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->id);

    $this->assertGuest();
});

test('Facebook login does not link an existing account by email', function () {
    $user = User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    Socialite::fake('facebook', FacebookUser::fake([
        'id' => 'facebook-user-456',
        'email' => 'existing@example.com',
    ]));

    $this->get(route('auth.facebook.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'An account with this email already exists. Log in with your email and password instead.',
        ]);

    $this->assertGuest();

    expect($user->fresh()->facebook_id)->toBeNull();
});

test('Facebook login rejects accounts without an email address', function () {
    Socialite::fake('facebook', FacebookUser::fake([
        'id' => 'facebook-user-789',
        'email' => null,
    ]));

    $this->get(route('auth.facebook.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'Facebook did not provide an email address. Please log in with your email and password.',
        ]);

    $this->assertGuest();

    expect(User::query()->count())->toBe(0);
});

test('Facebook login returns to the login page when authorization is canceled', function () {
    $this->get(route('auth.facebook.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'Facebook login was canceled. Please try again or log in with your email and password.',
        ]);

    $this->assertGuest();
});
