<?php

use App\Models\Category;
use App\Models\User;
use Database\Seeders\CategorySeeder;

test('the added categories are available in the upload selector', function () {
    $this->seed(CategorySeeder::class);
    $this->seed(CategorySeeder::class);

    expect(Category::whereIn('slug', ['kids', 'horror', 'anime', 'ai', 'others'])->count())->toBe(5);

    $response = $this->actingAs(User::factory()->create())->get(route('videos.create'));

    $response->assertOk()
        ->assertSee('Kids')
        ->assertSee('Horror')
        ->assertSee('Anime')
        ->assertSee('AI')
        ->assertSee('Others');
});
