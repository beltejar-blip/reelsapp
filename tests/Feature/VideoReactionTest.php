<?php

use App\Models\Category;
use App\Models\User;
use App\Models\Video;

test('a viewer can select, change, and remove a video reaction', function () {
    $viewer = User::factory()->create();
    $creator = User::factory()->create();
    $category = Category::create(['name' => 'Comedy', 'slug' => 'comedy']);
    $video = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'path' => 'video.mp4',
    ]);

    $loveResponse = $this->actingAs($viewer)->postJson(route('reels.like', $video), ['reaction' => 'love']);

    $loveResponse->assertOk()->assertJson(['liked' => true, 'reaction' => 'love', 'count' => 1]);
    $this->assertDatabaseHas('video_likes', [
        'video_id' => $video->id,
        'user_id' => $viewer->id,
        'reaction' => 'love',
    ]);

    $video->refresh();
    $feedResponse = $this->get(route('reels.index'));
    $feedResponse->assertOk()
        ->assertSee('data-reaction="love"', false)
        ->assertSee('❤️');

    $changeResponse = $this->postJson(route('reels.like', $video), ['reaction' => 'wow']);

    $changeResponse->assertOk()->assertJson(['liked' => true, 'reaction' => 'wow', 'count' => 1]);
    $this->assertDatabaseHas('video_likes', [
        'video_id' => $video->id,
        'user_id' => $viewer->id,
        'reaction' => 'wow',
    ]);

    $removeResponse = $this->postJson(route('reels.like', $video), ['reaction' => 'wow']);

    $removeResponse->assertOk()->assertJson(['liked' => false, 'reaction' => null, 'count' => 0]);
    $this->assertDatabaseMissing('video_likes', [
        'video_id' => $video->id,
        'user_id' => $viewer->id,
    ]);
});

test('the reaction endpoint rejects unsupported reactions without saving them', function () {
    $viewer = User::factory()->create();
    $creator = User::factory()->create();
    $category = Category::create(['name' => 'Comedy', 'slug' => 'comedy']);
    $video = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'path' => 'video.mp4',
    ]);

    $response = $this->actingAs($viewer)->postJson(route('reels.like', $video), ['reaction' => 'unsupported']);

    $response->assertUnprocessable()->assertInvalid(['reaction']);
    $this->assertDatabaseMissing('video_likes', [
        'video_id' => $video->id,
        'user_id' => $viewer->id,
    ]);
});

test('the like endpoint still toggles the default reaction when no reaction is supplied', function () {
    $viewer = User::factory()->create();
    $creator = User::factory()->create();
    $category = Category::create(['name' => 'Comedy', 'slug' => 'comedy']);
    $video = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'path' => 'video.mp4',
    ]);

    $firstResponse = $this->actingAs($viewer)->postJson(route('reels.like', $video));

    $firstResponse->assertOk()->assertJson(['liked' => true, 'reaction' => 'like', 'count' => 1]);
    $this->assertDatabaseHas('video_likes', [
        'video_id' => $video->id,
        'user_id' => $viewer->id,
        'reaction' => 'like',
    ]);

    $secondResponse = $this->postJson(route('reels.like', $video));

    $secondResponse->assertOk()->assertJson(['liked' => false, 'reaction' => null, 'count' => 0]);
    $this->assertDatabaseMissing('video_likes', [
        'video_id' => $video->id,
        'user_id' => $viewer->id,
    ]);
});
