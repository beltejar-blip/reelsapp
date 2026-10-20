@extends('reels.layout')

@section('title', 'Profile')

@section('content')
    <div class="page profile-page">
        <section class="profile-header">
            <div class="profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</div>
            <div class="profile-details">
                <div class="profile-title-row">
                    <div>
                        <h1 class="page-title">{{ $user->name }}</h1>
                        <p class="profile-email">{{ $user->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}"
                          onsubmit="return confirm('Are you sure you want to log out?')">
                        @csrf
                        <button type="submit" class="btn ghost small">Log out</button>
                    </form>
                </div>
                <div class="profile-stats" aria-label="Profile statistics">
                    <a href="{{ route('profile.following') }}"><strong>{{ $followingCount }}</strong> Following</a>
                    <span><strong>{{ $followerCount }}</strong> Followers</span>
                    <span><strong>{{ $likesReceivedCount }}</strong> Likes</span>
                </div>
                <a class="btn small profile-manage-link" href="{{ route('videos.index') }}">Manage uploaded videos</a>
            </div>
        </section>

        <nav class="profile-tabs" aria-label="Profile videos">
            <a href="{{ route('profile.index', ['tab' => 'videos']) }}"
               class="{{ $activeTab === 'videos' ? 'active' : '' }}"
               @if ($activeTab === 'videos') aria-current="page" @endif>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M10 8.5l6 3.5-6 3.5z"/></svg>
                Videos <span>{{ $videoCount }}</span>
            </a>
            <a href="{{ route('profile.index', ['tab' => 'favorites']) }}"
               class="{{ $activeTab === 'favorites' ? 'active' : '' }}"
               @if ($activeTab === 'favorites') aria-current="page" @endif>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12a1 1 0 0 1 1 1v17l-7-4-7 4V4a1 1 0 0 1 1-1z"/></svg>
                Favorites <span>{{ $favoriteCount }}</span>
            </a>
            <a href="{{ route('profile.index', ['tab' => 'liked']) }}"
               class="{{ $activeTab === 'liked' ? 'active' : '' }}"
               @if ($activeTab === 'liked') aria-current="page" @endif>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 00-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 00-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 000-7.8z"/></svg>
                Liked <span>{{ $likedCount }}</span>
            </a>
        </nav>

        @if ($videos->isEmpty())
            <div class="empty profile-empty">
                @if ($activeTab === 'videos')
                    <p>You have not uploaded any videos yet.</p>
                    <a class="btn" href="{{ route('videos.create') }}">Upload your first video</a>
                @elseif ($activeTab === 'favorites')
                    <p>You have not favorited any videos yet.</p>
                    <a class="btn" href="{{ route('reels.index') }}">Explore videos</a>
                @else
                    <p>You have not liked any videos yet.</p>
                    <a class="btn" href="{{ route('reels.index') }}">Explore videos</a>
                @endif
            </div>
        @else
            <div class="profile-video-grid">
                @foreach ($videos as $video)
                    <a class="profile-video-card" href="{{ route('reels.index', ['v' => $video->id]) }}"
                       aria-label="{{ $video->caption ?: 'Open video' }}">
                        <video src="{{ $video->url }}" muted preload="metadata" playsinline></video>
                        <span class="profile-video-overlay">
                            <span class="profile-video-caption">{{ $video->caption ?: $video->category->name }}</span>
                            <span class="profile-video-likes">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 00-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 00-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 000-7.8z"/></svg>
                                {{ $video->likes_count }}
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
