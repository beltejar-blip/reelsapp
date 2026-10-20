<?php

use App\Models\Category;
use App\Models\User;
use App\Models\Video;

test('a viewer can favorite and unfavorite a video', function () {
    $viewer = User::factory()->create();
    $creator = User::factory()->create();
    $category = Category::create(['name' => 'Comedy', 'slug' => 'comedy']);
    $video = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'path' => 'video.mp4',
    ]);

    $favoriteResponse = $this->actingAs($viewer)->postJson(route('reels.favorite', $video));

    $favoriteResponse->assertOk()->assertJson(['favorited' => true]);
    $this->assertDatabaseHas('video_favorites', [
        'user_id' => $viewer->id,
        'video_id' => $video->id,
    ]);

    $unfavoriteResponse = $this->deleteJson(route('reels.unfavorite', $video));

    $unfavoriteResponse->assertOk()->assertJson(['favorited' => false]);
    $this->assertDatabaseMissing('video_favorites', [
        'user_id' => $viewer->id,
        'video_id' => $video->id,
    ]);
});

test('the feed shows whether a video is favorited', function () {
    $viewer = User::factory()->create();
    $creator = User::factory()->create();
    $category = Category::create(['name' => 'Comedy', 'slug' => 'comedy']);
    $video = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'path' => 'video.mp4',
    ]);
    $viewer->favoriteVideos()->attach($video);

    $response = $this->actingAs($viewer)->get(route('reels.index'));

    $response->assertOk()
        ->assertSee('aria-label="Remove from favorites"', false)
        ->assertSee('Favorited');
});
