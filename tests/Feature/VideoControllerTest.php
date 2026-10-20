<?php

use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\CategorySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('AI-generated uploads are assigned to the AI category without requiring a category choice', function () {
    Storage::fake('public');
    $this->seed(CategorySeeder::class);
    $user = User::factory()->create();
    $aiCategory = Category::where('slug', 'ai')->firstOrFail();

    $response = $this->actingAs($user)->post(route('videos.store'), [
        'video' => UploadedFile::fake()->create('generated.mp4', 100, 'video/mp4'),
        'caption' => 'Generated clip',
        'is_ai_generated' => '1',
    ]);

    $response->assertRedirect(route('videos.index'));
    $this->assertDatabaseHas('videos', [
        'user_id' => $user->id,
        'category_id' => $aiCategory->id,
        'caption' => 'Generated clip',
        'is_ai_generated' => true,
    ]);
});

test('marking an existing video as AI-generated moves it to the AI category', function () {
    $this->seed(CategorySeeder::class);
    $user = User::factory()->create();
    $horrorCategory = Category::where('slug', 'horror')->firstOrFail();
    $aiCategory = Category::where('slug', 'ai')->firstOrFail();
    $video = Video::create([
        'user_id' => $user->id,
        'category_id' => $horrorCategory->id,
        'caption' => 'Edited clip',
        'path' => 'videos/edited.mp4',
    ]);

    $response = $this->actingAs($user)->put(route('videos.update', $video), [
        'category_id' => $horrorCategory->id,
        'caption' => 'Edited clip',
        'is_ai_generated' => '1',
    ]);

    $response->assertRedirect(route('videos.index'));
    $this->assertDatabaseHas('videos', [
        'id' => $video->id,
        'category_id' => $aiCategory->id,
        'is_ai_generated' => true,
    ]);
});
