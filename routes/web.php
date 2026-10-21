<?php

use App\Http\Controllers\Auth\LoginOtpController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReelController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

// Startup page: go straight to the log in form (signed-in users are sent on to the Reels feed).
Route::redirect('/', '/login')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('email-otp', [LoginOtpController::class, 'show'])->middleware('throttle:login-otp')->name('email-otp.challenge');
    Route::post('email-otp', [LoginOtpController::class, 'verify'])->name('email-otp.verify');
    Route::post('email-otp/resend', [LoginOtpController::class, 'resend'])->name('email-otp.resend');
});

// After login the "dashboard" is the Reels feed.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/reels')->name('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::get('profile/favorites', [ProfileController::class, 'favorites'])->name('profile.favorites');
    Route::get('profile/following', [ProfileController::class, 'following'])->name('profile.following');

    // Reels feed + actions (JSON)
    Route::get('reels', [ReelController::class, 'index'])->name('reels.index');
    Route::post('reels/preference', [ReelController::class, 'preference'])->name('reels.preference');
    Route::post('reels/mute/{user}', [ReelController::class, 'mute'])->name('reels.mute');
    Route::post('reels/creators/{user}/follow', [ReelController::class, 'follow'])->name('reels.creators.follow');
    Route::delete('reels/creators/{user}/follow', [ReelController::class, 'unfollow'])->name('reels.creators.unfollow');
    Route::delete('reels/comments/{comment}', [ReelController::class, 'destroyComment'])->name('reels.comments.destroy');
    Route::post('reels/{video}/like', [ReelController::class, 'like'])->name('reels.like');
    Route::post('reels/{video}/favorite', [ReelController::class, 'favorite'])->name('reels.favorite');
    Route::delete('reels/{video}/favorite', [ReelController::class, 'unfavorite'])->name('reels.unfavorite');
    Route::get('reels/{video}/comments', [ReelController::class, 'comments'])->name('reels.comments');
    Route::post('reels/{video}/comments', [ReelController::class, 'storeComment'])->name('reels.comments.store');
    Route::post('reels/{video}/report', [ReelController::class, 'report'])->name('reels.report');

    // My videos: list, upload, edit, delete
    Route::resource('videos', VideoController::class)->except(['show']);
});

require __DIR__.'/settings.php';
