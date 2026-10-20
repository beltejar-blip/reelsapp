@extends('reels.layout')

@section('title', 'Favorites')

@section('content')
    <div class="page">
        <div class="page-head">
            <h1 class="page-title">Favorites</h1>
            <a class="btn ghost small" href="{{ route('profile.index') }}">Profile</a>
        </div>

        @if ($videos->isEmpty())
            <div class="empty">
                <p>You have not favorited any videos yet.</p>
                <a class="btn" href="{{ route('reels.index') }}">Explore videos</a>
            </div>
        @else
            <div class="grid">
                @foreach ($videos as $video)
                    <div class="tile">
                        <video src="{{ $video->url }}" controls preload="metadata" playsinline></video>
                        <div class="tile-body">
                            <div>
                                <span class="badge">{{ $video->category->name }}</span>
                                @if ($video->is_ai_generated)
                                    <span class="badge ai">AI</span>
                                @endif
                            </div>
                            @if ($video->caption)
                                <p class="tile-caption">{{ $video->caption }}</p>
                            @endif
                            <p class="favorite-owner">By {{ $video->user->name }}</p>
                            <p class="tile-stats">{{ $video->likes_count }} likes &middot; {{ $video->comments_count }} comments</p>
                            <a class="btn ghost small" href="{{ route('reels.index', ['v' => $video->id]) }}">Open video</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
