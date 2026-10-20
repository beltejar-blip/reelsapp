<?php

use App\Models\Category;
use App\Models\User;
use App\Models\Video;

test('the reels search filters videos by caption', function () {
    $viewer = User::factory()->create();
    $creator = User::factory()->create();
    $category = Category::create(['name' => 'Travel', 'slug' => 'travel']);
    $matchingVideo = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'caption' => 'Sunset beside the ocean',
        'path' => 'sunset.mp4',
    ]);
    $otherVideo = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'caption' => 'Mountain trail',
        'path' => 'mountain.mp4',
    ]);

    $response = $this->actingAs($viewer)->get(route('reels.index', [
        'category' => 'travel',
        'search' => 'Sunset',
    ]));

    $response->assertOk()
        ->assertSee('reel-'.$matchingVideo->id, false)
        ->assertDontSee('reel-'.$otherVideo->id, false)
        ->assertSee('name="category" value="travel"', false)
        ->assertSee(e(route('reels.index', ['category' => 'travel', 'search' => 'Sunset'])), false);
});

test('the reels search matches creator and category names', function () {
    $viewer = User::factory()->create();
    $creator = User::factory()->create(['name' => 'Creator Needle']);
    $category = Category::create(['name' => 'Category Needle', 'slug' => 'category-needle']);
    $video = Video::create([
        'user_id' => $creator->id,
        'category_id' => $category->id,
        'caption' => 'A video',
        'path' => 'video.mp4',
    ]);

    $creatorSearch = $this->actingAs($viewer)->get(route('reels.index', ['search' => 'Creator Needle']));
    $creatorSearch->assertOk()->assertSee('reel-'.$video->id, false);

    $categorySearch = $this->get(route('reels.index', ['search' => 'Category Needle']));
    $categorySearch->assertOk()->assertSee('reel-'.$video->id, false);
});

test('the reels search rejects non-string input', function () {
    $viewer = User::factory()->create();

    $response = $this->actingAs($viewer)->get(route('reels.index', ['search' => ['unexpected']]));

    $response->assertRedirect();
    $response->assertSessionHasErrors('search');
});
