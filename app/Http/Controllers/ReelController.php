<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\Video;
use App\Models\VideoComment;
use App\Models\VideoLike;
use App\Models\VideoReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReelController extends Controller
{
    /** How many "unlabeled AI" reports automatically label a video as AI-generated. */
    private const REPORTS_TO_LABEL = 3;

    /** The Reels feed (the dashboard). */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $mode = $this->aiMode($user);
        $searchTerm = trim($filters['search'] ?? '');

        $category = null;
        if (! empty($filters['category'])) {
            $category = Category::where('slug', $filters['category'])->first();
        }

        $mutedIds = DB::table('muted_creators')->where('user_id', $user->id)->pluck('muted_user_id');

        $query = Video::query()
            ->with(['user', 'category'])
            ->with(['likes' => fn ($query) => $query->where('user_id', $user->id)])
            ->withCount(['likes', 'comments'])
            ->withExists(['likes as liked_by_me' => fn ($q) => $q->where('user_id', $user->id)])
            ->withExists(['favoritedBy as favorited_by_me' => fn ($q) => $q->where('users.id', $user->id)])
            ->withExists([
                'user as followed_by_me' => fn ($query) => $query->whereHas(
                    'followers',
                    fn ($followers) => $followers->whereKey($user->id)
                ),
            ])
            ->whereNotIn('user_id', $mutedIds);

        if ($category) {
            $query->where('category_id', $category->id);
        }

        if ($searchTerm !== '') {
            $searchPattern = '%'.$searchTerm.'%';
            $query->where(function ($query) use ($searchPattern) {
                $query->where('caption', 'like', $searchPattern)
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', $searchPattern))
                    ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', $searchPattern));
            });
        }

        if ($mode === 'hide') {
            $query->where('is_ai_generated', false);
        } elseif ($mode === 'less') {
            $query->orderBy('is_ai_generated'); // human-made first, AI-labelled last
        }

        return view('reels.index', [
            'videos' => $query->latest('id')->limit(30)->get(),
            'categories' => Category::orderBy('name')->get(),
            'activeCategory' => $category?->slug,
            'searchTerm' => $searchTerm,
            'mode' => $mode,
        ]);
    }

    /** Toggle like. */
    public function like(Request $request, Video $video): JsonResponse
    {
        $userId = $request->user()->id;
        $data = $request->validate([
            'reaction' => ['sometimes', 'nullable', 'in:like,love,care,haha,wow,sad,angry'],
        ]);
        $like = VideoLike::where('video_id', $video->id)->where('user_id', $userId)->first();
        $reaction = array_key_exists('reaction', $data) ? $data['reaction'] : 'like';

        if ($reaction === null || ($like && $like->reaction === $reaction)) {
            $like?->delete();
            $reaction = null;
        } else {
            if ($like) {
                $like->update(['reaction' => $reaction]);
            } else {
                VideoLike::create([
                    'video_id' => $video->id,
                    'user_id' => $userId,
                    'reaction' => $reaction,
                ]);
            }
        }

        return response()->json([
            'liked' => $reaction !== null,
            'reaction' => $reaction,
            'count' => $video->likes()->count(),
        ]);
    }

    /** List comments (newest first). */
    public function comments(Request $request, Video $video): JsonResponse
    {
        $userId = $request->user()->id;

        $list = $video->comments()->with('user')->latest('id')->limit(100)->get()
            ->map(fn (VideoComment $comment) => $this->commentPayload($comment, $userId))
            ->values();

        return response()->json($list);
    }

    /** Create a comment. */
    public function storeComment(Request $request, Video $video): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:500'],
        ]);

        $comment = $video->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);
        $comment->load('user');

        return response()->json([
            'comment' => $this->commentPayload($comment, $request->user()->id),
            'count' => $video->comments()->count(),
        ], 201);
    }

    /** Delete a comment (its author or the video owner). */
    public function destroyComment(Request $request, VideoComment $comment): JsonResponse
    {
        $video = $comment->video;
        $userId = $request->user()->id;

        abort_unless($comment->user_id === $userId || $video->user_id === $userId, 403);

        $comment->delete();

        return response()->json(['count' => $video->comments()->count()]);
    }

    /** Report a video that should be labelled as AI-generated. */
    public function report(Request $request, Video $video): JsonResponse
    {
        $userId = $request->user()->id;

        abort_if($video->user_id === $userId, 422, 'You cannot report your own video.');

        $data = $request->validate([
            'reason' => ['required', 'in:unlabeled_ai,inappropriate,harassment,spam,other'],
        ]);

        VideoReport::updateOrCreate(
            ['video_id' => $video->id, 'user_id' => $userId],
            ['reason' => $data['reason']]
        );

        $labelled = false;
        if ($data['reason'] === 'unlabeled_ai'
            && ! $video->is_ai_generated
            && $video->reports()->where('reason', 'unlabeled_ai')->count() >= self::REPORTS_TO_LABEL) {
            $video->update([
                'category_id' => Category::where('slug', 'ai')->firstOrFail()->id,
                'is_ai_generated' => true,
                'ai_label_source' => 'reported',
            ]);
            $labelled = true;
        }

        return response()->json(['ok' => true, 'labelled' => $labelled]);
    }

    /** "Not interested in this creator". */
    public function mute(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot mute yourself.');

        DB::table('muted_creators')->insertOrIgnore([
            'user_id' => $request->user()->id,
            'muted_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function follow(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot follow yourself.');

        $request->user()->following()->syncWithoutDetaching([$user->id]);

        return response()->json(['following' => true]);
    }

    public function unfollow(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot unfollow yourself.');

        $request->user()->following()->detach($user->id);

        return response()->json(['following' => false]);
    }

    public function favorite(Request $request, Video $video): JsonResponse
    {
        $request->user()->favoriteVideos()->syncWithoutDetaching([$video->id]);

        return response()->json(['favorited' => true]);
    }

    public function unfavorite(Request $request, Video $video): JsonResponse
    {
        $request->user()->favoriteVideos()->detach($video->id);

        return response()->json(['favorited' => false]);
    }

    /** Save the AI-content setting: show, less or hide. */
    public function preference(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ai_content_mode' => ['required', 'in:show,less,hide'],
        ]);

        UserPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['ai_content_mode' => $data['ai_content_mode']]
        );

        return response()->json(['ok' => true]);
    }

    private function aiMode(User $user): string
    {
        return UserPreference::where('user_id', $user->id)->value('ai_content_mode') ?? 'show';
    }

    private function commentPayload(VideoComment $comment, int $currentUserId): array
    {
        return [
            'id' => $comment->id,
            'user' => $comment->user->name,
            'initial' => mb_strtoupper(mb_substr($comment->user->name, 0, 1)),
            'body' => $comment->body,
            'ago' => $comment->created_at->diffForHumans(),
            'mine' => $comment->user_id === $currentUserId,
        ];
    }
}
