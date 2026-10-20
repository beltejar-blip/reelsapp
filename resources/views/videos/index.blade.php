@extends('reels.layout')

@section('title', 'My videos')

@section('content')
    <div class="page">
        <div class="page-head">
            <h1 class="page-title">My videos</h1>
            <a class="btn small" href="{{ route('videos.create') }}">+ Upload</a>
        </div>

        @if (session('status'))
            <div class="alert ok">{{ session('status') }}</div>
        @endif

        @if ($videos->isEmpty())
            <div class="empty">
                <p>You have not uploaded any videos yet.</p>
                <a class="btn" href="{{ route('videos.create') }}">Upload your first video</a>
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
                            <p class="tile-stats">{{ $video->likes_count }} likes &middot; {{ $video->comments_count }} comments</p>
                            <div class="tile-actions">
                                <a class="btn ghost small" href="{{ route('videos.edit', $video) }}">Edit</a>
                                <form method="POST" action="{{ route('videos.destroy', $video) }}"
                                      onsubmit="return confirm('Delete this video? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn danger small">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
