<?php

use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\CategorySeeder;

test('a viewer can follow and unfollow a creator', function () {
    $viewer = User::factory()->create();
    $creator = User::factory()->create();

    $followResponse = $this->actingAs($viewer)->postJson(route('reels.creators.follow', $creator));

    $followResponse->assertOk()->assertJson(['following' => true]);
    $this->assertDatabaseHas('creator_follows', [
        'follower_id' => $viewer->id,
        'creator_id' => $creator->id,
    ]);

    $unfollowResponse = $this->deleteJson(route('reels.creators.unfollow', $creator));

    $unfollowResponse->assertOk()->assertJson(['following' => false]);
    $this->assertDatabaseMissing('creator_follows', [
        'follower_id' => $viewer->id,
        'creator_id' => $creator->id,
    ]);
});

test('a user cannot follow themselves', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('reels.creators.follow', $user));

    $response->assertUnprocessable();
    $this->assertDatabaseMissing('creator_follows', [
        'follower_id' => $user->id,
        'creator_id' => $user->id,
    ]);
});

test('the feed shows follow state and a report button for another creators unlabeled video', function () {
    $viewer = User::factory()->create();
    $creator = User::factory()->create();
    $category = Category::create(['name' => 'Comedy', 'slug' => 'comedy']);
    $video = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'path' => 'video.mp4',
        'is_ai_generated' => false,
    ]);
    $viewer->following()->attach($creator);

    $response = $this->actingAs($viewer)->get(route('reels.index'));

    $response->assertOk()
        ->assertSee('Following')
        ->assertSee('data-act="report"', false)
        ->assertSee('data-follow-url="'.route('reels.creators.follow', $creator).'"', false)
        ->assertSee('Why are you reporting this video?')
        ->assertSee(route('profile.index'), false)
        ->assertSee('reel-'.$video->id, false);
});

test('the report button submits to the existing video report action', function () {
    $viewer = User::factory()->create();
    $creator = User::factory()->create();
    $category = Category::create(['name' => 'Comedy', 'slug' => 'comedy']);
    $video = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'path' => 'video.mp4',
    ]);

    $response = $this->actingAs($viewer)->postJson(route('reels.report', $video), [
        'reason' => 'unlabeled_ai',
    ]);

    $response->assertOk()->assertJson(['ok' => true, 'labelled' => false]);
    $this->assertDatabaseHas('video_reports', [
        'video_id' => $video->id,
        'user_id' => $viewer->id,
        'reason' => 'unlabeled_ai',
    ]);
});

test('a viewer can choose a reason when reporting a video', function () {
    $viewer = User::factory()->create();
    $creator = User::factory()->create();
    $category = Category::create(['name' => 'Comedy', 'slug' => 'comedy']);
    $video = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'path' => 'video.mp4',
    ]);

    $response = $this->actingAs($viewer)->postJson(route('reels.report', $video), [
        'reason' => 'spam',
    ]);

    $response->assertOk()->assertJson(['ok' => true, 'labelled' => false]);
    $this->assertDatabaseHas('video_reports', [
        'video_id' => $video->id,
        'user_id' => $viewer->id,
        'reason' => 'spam',
    ]);
});

test('an AI-labeled video is moved into the AI category after enough reports', function () {
    $this->seed(CategorySeeder::class);
    $creator = User::factory()->create();
    $category = Category::where('slug', 'comedy')->firstOrFail();
    $aiCategory = Category::where('slug', 'ai')->firstOrFail();
    $video = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'path' => 'video.mp4',
    ]);

    foreach (User::factory()->count(3)->create() as $index => $reporter) {
        $response = $this->actingAs($reporter)->postJson(route('reels.report', $video), [
            'reason' => 'unlabeled_ai',
        ]);

        $response->assertOk()->assertJson(['labelled' => $index === 2]);
    }

    $this->assertDatabaseHas('videos', [
        'id' => $video->id,
        'category_id' => $aiCategory->id,
        'is_ai_generated' => true,
        'ai_label_source' => 'reported',
    ]);
});

test('a report requires a supported reason', function () {
    $viewer = User::factory()->create();
    $creator = User::factory()->create();
    $category = Category::create(['name' => 'Comedy', 'slug' => 'comedy']);
    $video = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'path' => 'video.mp4',
    ]);

    $response = $this->actingAs($viewer)->postJson(route('reels.report', $video), [
        'reason' => 'not-a-real-reason',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->assertDatabaseMissing('video_reports', [
        'video_id' => $video->id,
        'user_id' => $viewer->id,
    ]);
});
