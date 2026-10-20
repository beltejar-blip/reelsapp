<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Video;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * CRUD for the signed-in user's own videos.
 * index = list, create/store = upload, edit/update = change details, destroy = delete.
 */
class VideoController extends Controller
{
    /** Largest video the app accepts, in bytes (100 MB). */
    private const APP_MAX_BYTES = 100 * 1024 * 1024;

    public function index(Request $request): View
    {
        return view('videos.index', [
            'videos' => $request->user()->videos()
                ->with('category')
                ->withCount(['likes', 'comments'])
                ->latest('id')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $maxUploadBytes = $this->maxUploadBytes();

        return view('videos.create', [
            'categories' => Category::orderBy('name')->get(),
            'maxUploadBytes' => $maxUploadBytes,
            'maxUploadLabel' => $this->formatFileSize($maxUploadBytes),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'video' => ['required', 'file', 'mimes:mp4,webm,mov', 'max:'.(self::APP_MAX_BYTES / 1024)],
            'category_id' => [$request->boolean('is_ai_generated') ? 'nullable' : 'required', 'exists:categories,id'],
            'caption' => ['nullable', 'string', 'max:255'],
            'is_ai_generated' => ['nullable', 'boolean'],
        ], [
            'video.uploaded' => 'The video could not be uploaded. It is probably bigger than the server limit ('
                .$this->formatFileSize($this->maxUploadBytes()).'). Raise upload_max_filesize and post_max_size in php.ini, then restart php artisan serve.',
            'video.mimes' => 'The video must be an mp4, webm or mov file.',
            'video.max' => 'The video must be 100 MB or smaller.',
        ]);

        $isAiGenerated = $request->boolean('is_ai_generated');
        $categoryId = $isAiGenerated ? $this->aiCategory()->id : $data['category_id'];
        $path = $request->file('video')->store('videos', 'public');

        Video::create([
            'user_id' => $request->user()->id,
            'category_id' => $categoryId,
            'caption' => $data['caption'] ?? null,
            'path' => $path,
            'is_ai_generated' => $isAiGenerated,
            'ai_label_source' => 'creator',
        ]);

        return redirect()->route('videos.index')->with('status', 'Video uploaded!');
    }

    public function edit(Request $request, Video $video): View
    {
        $this->ownVideo($request, $video);

        return view('videos.edit', [
            'video' => $video,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Video $video): RedirectResponse
    {
        $this->ownVideo($request, $video);

        $isAi = $request->boolean('is_ai_generated') || $video->ai_label_source === 'reported';

        $data = $request->validate([
            'category_id' => [$isAi ? 'nullable' : 'required', 'exists:categories,id'],
            'caption' => ['nullable', 'string', 'max:255'],
            'is_ai_generated' => ['nullable', 'boolean'],
        ]);

        $source = $video->ai_label_source;

        if ($video->ai_label_source !== 'reported' && $isAi !== $video->is_ai_generated) {
            $source = 'creator';
        }

        $video->update([
            'category_id' => $isAi ? $this->aiCategory()->id : $data['category_id'],
            'caption' => $data['caption'] ?? null,
            'is_ai_generated' => $isAi,
            'ai_label_source' => $source,
        ]);

        return redirect()->route('videos.index')->with('status', 'Video updated.');
    }

    public function destroy(Request $request, Video $video): RedirectResponse
    {
        $this->ownVideo($request, $video);

        Storage::disk('public')->delete($video->path);
        $video->delete();

        return redirect()->route('videos.index')->with('status', 'Video deleted.');
    }

    private function ownVideo(Request $request, Video $video): void
    {
        abort_unless($video->user_id === $request->user()->id, 403);
    }

    private function aiCategory(): Category
    {
        return Category::where('slug', 'ai')->firstOrFail();
    }

    /** The smaller of the app limit and the PHP upload limits. */
    private function maxUploadBytes(): int
    {
        $limits = array_filter([
            $this->iniToBytes(ini_get('upload_max_filesize')),
            $this->iniToBytes(ini_get('post_max_size')),
        ]);

        return min([...$limits, self::APP_MAX_BYTES]);
    }

    private function formatFileSize(int $bytes): string
    {
        $size = (float) $bytes;

        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($size < 1024 || $unit === 'GB') {
                $formattedSize = $unit === 'B'
                    ? (string) (int) $size
                    : rtrim(rtrim(number_format($size, 2, '.', ''), '0'), '.');

                return $formattedSize.' '.$unit;
            }

            $size /= 1024;
        }

        return $bytes.' B';
    }

    private function iniToBytes(string|false $value): int
    {
        if ($value === false || trim($value) === '') {
            return 0;
        }

        $value = trim($value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
