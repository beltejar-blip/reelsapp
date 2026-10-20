<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VideoLike;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->validate([
            'tab' => ['nullable', 'in:videos,favorites,liked'],
        ]);

        return $this->showTab($request, $filters['tab'] ?? 'videos');
    }

    public function favorites(Request $request): View
    {
        return $this->showTab($request, 'favorites');
    }

    public function following(Request $request): View
    {
        $following = $request->user()->following()
            ->orderBy('users.name')
            ->get(['users.id', 'users.name']);

        return view('profile.following', ['following' => $following]);
    }

    private function showTab(Request $request, string $activeTab): View
    {
        $user = $request->user();

        return view('profile.index', [
            'user' => $user,
            'activeTab' => $activeTab,
            'videos' => $this->videosForTab($user, $activeTab),
            'favoriteCount' => $user->favoriteVideos()->count(),
            'videoCount' => $user->videos()->count(),
            'likedCount' => $user->likedVideos()->count(),
            'followingCount' => $user->following()->count(),
            'followerCount' => $user->followers()->count(),
            'likesReceivedCount' => VideoLike::whereHas(
                'video',
                fn ($query) => $query->where('user_id', $user->id)
            )->count(),
        ]);
    }

    private function videosForTab(User $user, string $activeTab): Collection
    {
        $videos = match ($activeTab) {
            'favorites' => $user->favoriteVideos()
                ->orderByPivot('created_at', 'desc'),
            'liked' => $user->likedVideos()
                ->orderByPivot('created_at', 'desc'),
            default => $user->videos()->latest('id'),
        };

        return $videos
            ->with(['category', 'user'])
            ->withCount(['likes', 'comments'])
            ->get();
    }
}
