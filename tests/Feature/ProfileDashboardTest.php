<?php

use App\Models\Category;
use App\Models\User;
use App\Models\Video;

test('the profile dashboard shows video tabs, profile statistics, and logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('profile.index'));

    $response->assertOk()
        ->assertSee('Videos')
        ->assertSee('Favorites')
        ->assertSee('Liked')
        ->assertSee('Following')
        ->assertSee('Followers')
        ->assertSee('Log out')
        ->assertSee('onsubmit="return confirm(\'Are you sure you want to log out?\')"', false)
        ->assertSee(route('profile.index', ['tab' => 'favorites']), false)
        ->assertSee(route('profile.index', ['tab' => 'liked']), false)
        ->assertSee(route('profile.following'), false)
        ->assertSee(route('videos.index'), false);
});

test('the profile favorites tab only displays the signed-in user favorites', function () {
    $viewer = User::factory()->create();
    $creator = User::factory()->create();
    $category = Category::create(['name' => 'Comedy', 'slug' => 'comedy']);
    $favorite = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'caption' => 'Saved video',
        'path' => 'saved.mp4',
    ]);
    $notFavorite = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'caption' => 'Not saved video',
        'path' => 'not-saved.mp4',
    ]);
    $viewer->favoriteVideos()->attach($favorite);

    $response = $this->actingAs($viewer)->get(route('profile.favorites'));

    $response->assertOk()
        ->assertSee('aria-current="page"', false)
        ->assertSee('Saved video')
        ->assertDontSee('Not saved video')
        ->assertSee(route('reels.index', ['v' => $favorite->id]), false);
});

test('the profile videos tab only displays videos uploaded by the signed-in user', function () {
    $viewer = User::factory()->create();
    $otherUser = User::factory()->create();
    $category = Category::create(['name' => 'Comedy', 'slug' => 'comedy']);
    $ownVideo = Video::create([
        'user_id' => $viewer->id,
        'category_id' => $category->id,
        'caption' => 'My uploaded video',
        'path' => 'mine.mp4',
    ]);
    Video::create([
        'user_id' => $otherUser->id,
        'category_id' => $category->id,
        'caption' => 'Someone elses video',
        'path' => 'other.mp4',
    ]);

    $response = $this->actingAs($viewer)->get(route('profile.index', ['tab' => 'videos']));

    $response->assertOk()
        ->assertSee('My uploaded video')
        ->assertDontSee('Someone elses video')
        ->assertSee(route('reels.index', ['v' => $ownVideo->id]), false);
});

test('the liked tab only displays videos liked by the signed-in user', function () {
    $viewer = User::factory()->create();
    $creator = User::factory()->create();
    $category = Category::create(['name' => 'Comedy', 'slug' => 'comedy']);
    $likedVideo = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'caption' => 'Liked video',
        'path' => 'liked.mp4',
    ]);
    $otherVideo = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'caption' => 'Unliked video',
        'path' => 'unliked.mp4',
    ]);
    $viewer->likedVideos()->attach($likedVideo, ['reaction' => 'love']);

    $response = $this->actingAs($viewer)->get(route('profile.index', ['tab' => 'liked']));

    $response->assertOk()
        ->assertSee('Liked video')
        ->assertDontSee('Unliked video')
        ->assertSee(route('reels.index', ['v' => $likedVideo->id]), false);
});

test('profile tabs require authentication', function () {
    $this->get(route('profile.index'))->assertRedirect(route('login'));
    $this->get(route('profile.favorites'))->assertRedirect(route('login'));
    $this->get(route('profile.following'))->assertRedirect(route('login'));
    $this->get(route('profile.index', ['tab' => 'liked']))->assertRedirect(route('login'));
});

test('the following page lists only accounts the signed-in user follows', function () {
    $viewer = User::factory()->create();
    $followedCreator = User::factory()->create(['name' => 'Followed creator']);
    $otherCreator = User::factory()->create(['name' => 'Other creator']);
    $viewer->following()->attach($followedCreator);

    $response = $this->actingAs($viewer)->get(route('profile.following'));

    $response->assertOk()
        ->assertSee('Followed creator')
        ->assertDontSee('Other creator');
});
