@extends('reels.layout')

@section('title', 'Reels')

@section('content')
    {{-- Splash logo (shows once per browser session, like Shopee) --}}
    <div id="splash">
        <div class="splash-logo">
            <svg viewBox="0 0 48 48"><rect width="48" height="48" rx="12" fill="#2d88ff"/><path d="M19 15l14 9-14 9z" fill="#fff"/></svg>
            <strong>Reels</strong>
        </div>
    </div>

    <header class="topbar">
        <div class="topbar-row">
            <span class="logo">
                <svg viewBox="0 0 48 48"><rect width="48" height="48" rx="12" fill="#2d88ff"/><path d="M19 15l14 9-14 9z" fill="#fff"/></svg>
                Reels
            </span>
            <div class="topbar-actions">
                <button type="button" id="ai-pill" class="pill mode-{{ $mode }}" data-mode="{{ $mode }}"
                        title="AI videos: Show = normal, Less = AI videos last, Hide = no AI videos. Tap to change.">
                    AI: {{ ucfirst($mode) }}
                </button>
                <button type="button" id="sound-btn" class="icon-btn sound-btn off" aria-label="Unmute">
                    <svg class="off-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4z"/><path d="M23 9l-6 6M17 9l6 6"/></svg>
                    <svg class="on-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 010 7M19 5a10 10 0 010 14"/></svg>
                </button>
            </div>
        </div>
        <form class="feed-search" method="GET" action="{{ route('reels.index') }}" role="search">
            @if ($activeCategory)
                <input type="hidden" name="category" value="{{ $activeCategory }}">
            @endif
            <label class="feed-search-label" for="feed-search-input">Search videos, creators, or categories</label>
            <input id="feed-search-input" type="search" name="search" value="{{ $searchTerm }}"
                   placeholder="Search videos, creators, or categories" maxlength="100">
            <button type="submit" aria-label="Search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
            </button>
        </form>
        <div class="chips">
            <a class="chip {{ $activeCategory ? '' : 'active' }}" href="{{ route('reels.index', ['search' => $searchTerm ?: null]) }}">All</a>
            @foreach ($categories as $category)
                <a class="chip {{ $activeCategory === $category->slug ? 'active' : '' }}"
                   href="{{ route('reels.index', ['category' => $category->slug, 'search' => $searchTerm ?: null]) }}">{{ $category->name }}</a>
            @endforeach
        </div>
    </header>

    <main class="feed" id="feed" data-base="{{ url('/reels') }}">
        @forelse ($videos as $video)
            <section class="reel" id="reel-{{ $video->id }}"
                     data-id="{{ $video->id }}"
                     data-user="{{ $video->user_id }}"
                     data-creator="{{ $video->user->name }}"
                     data-mine="{{ $video->user_id === auth()->id() ? '1' : '0' }}"
                     data-ai="{{ $video->is_ai_generated ? '1' : '0' }}"
                     data-follow-url="{{ route('reels.creators.follow', $video->user) }}"
                     data-unfollow-url="{{ route('reels.creators.unfollow', $video->user) }}"
                     data-favorite-url="{{ route('reels.favorite', $video) }}"
                     data-unfavorite-url="{{ route('reels.unfavorite', $video) }}"
                     data-edit="{{ route('videos.edit', $video) }}">
                <video class="reel-video" src="{{ $video->url }}" loop muted playsinline preload="metadata"></video>
                <div class="reel-shade"></div>
                <div class="tap"></div>
                <div class="play-flash">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                </div>
                <div class="pop-heart">
                    <svg viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 00-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 00-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 000-7.8z"/></svg>
                </div>

                <div class="rail">
                    @php
                        $currentReaction = $video->likes->first()?->reaction;
                        $reactionOptions = [
                            'like' => ['emoji' => '👍', 'label' => 'Like'],
                            'love' => ['emoji' => '❤️', 'label' => 'Love'],
                            'care' => ['emoji' => '🤗', 'label' => 'Care'],
                            'haha' => ['emoji' => '😂', 'label' => 'Haha'],
                            'wow' => ['emoji' => '😮', 'label' => 'Wow'],
                            'sad' => ['emoji' => '😢', 'label' => 'Sad'],
                            'angry' => ['emoji' => '😡', 'label' => 'Angry'],
                        ];
                    @endphp
                    <div class="like-control">
                        <button type="button" class="fullscreen-btn" data-fullscreen
                                aria-label="View video fullscreen" title="View video fullscreen">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m13-5h3a2 2 0 0 1 2 2v3M3 16v3a2 2 0 0 0 2 2h3m13-5v3a2 2 0 0 1-2 2h-3"/></svg>
                        </button>
                        <div class="reaction-picker" role="group" aria-label="React to this video">
                            @foreach ($reactionOptions as $reaction => $option)
                                <button type="button" class="reaction-option" data-act="react"
                                        data-reaction="{{ $reaction }}" data-emoji="{{ $option['emoji'] }}"
                                        aria-label="{{ $option['label'] }}" title="{{ $option['label'] }}"
                                        aria-pressed="{{ $currentReaction === $reaction ? 'true' : 'false' }}">{{ $option['emoji'] }}</button>
                            @endforeach
                        </div>
                        <button type="button"
                                class="rail-btn {{ $video->liked_by_me ? 'liked' : '' }}"
                                data-act="like" data-reaction="{{ $currentReaction ?? '' }}"
                                aria-label="{{ $currentReaction ? 'Remove '.$reactionOptions[$currentReaction]['label'].' reaction' : 'Like' }}"
                                title="Press and hold for reactions"
                                aria-pressed="{{ $video->liked_by_me ? 'true' : 'false' }}">
                            <span class="ic">
                                <span class="reaction-current" aria-hidden="true">{{ $currentReaction ? $reactionOptions[$currentReaction]['emoji'] : '' }}</span>
                                <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 00-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 00-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 000-7.8z"/></svg>
                            </span>
                            <span class="count">{{ $video->likes_count }}</span>
                        </button>
                    </div>
                    <button type="button" class="rail-btn" data-act="comment" aria-label="Comments">
                        <span class="ic"><svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 01-9 8.4 8.5 8.5 0 01-3.8-.9L3 21l1.9-5.2A8.4 8.4 0 1121 11.5z"/></svg></span>
                        <span class="count">{{ $video->comments_count }}</span>
                    </button>
                    <button type="button" class="rail-btn" data-act="share" aria-label="Share">
                        <span class="ic"><svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v7a1 1 0 001 1h14a1 1 0 001-1v-7M16 6l-4-4-4 4M12 2v13"/></svg></span>
                        <span>Share</span>
                    </button>
                    <button type="button" class="rail-btn {{ $video->favorited_by_me ? 'favorited' : '' }}"
                            data-act="favorite" aria-label="{{ $video->favorited_by_me ? 'Remove from favorites' : 'Add to favorites' }}"
                            aria-pressed="{{ $video->favorited_by_me ? 'true' : 'false' }}">
                        <span class="ic"><svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12a1 1 0 0 1 1 1v17l-7-4-7 4V4a1 1 0 0 1 1-1z"/></svg></span>
                        <span>{{ $video->favorited_by_me ? 'Favorited' : 'Favorite' }}</span>
                    </button>
                    <button type="button" class="rail-btn" data-act="more" aria-label="More options">
                        <span class="ic"><svg viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.8" fill="currentColor"/><circle cx="12" cy="12" r="1.8" fill="currentColor"/><circle cx="19" cy="12" r="1.8" fill="currentColor"/></svg></span>
                        <span>More</span>
                    </button>
                </div>

                <div class="meta">
                    <div class="creator">
                        <span class="avatar">{{ mb_strtoupper(mb_substr($video->user->name, 0, 1)) }}</span>
                        <strong>{{ $video->user->name }}</strong>
                        @if ($video->user_id !== auth()->id())
                            <button type="button" class="follow-btn {{ $video->followed_by_me ? 'following' : '' }}"
                                    data-act="follow" aria-pressed="{{ $video->followed_by_me ? 'true' : 'false' }}">
                                {{ $video->followed_by_me ? 'Following' : 'Follow' }}
                            </button>
                            @if (! $video->is_ai_generated)
                                <button type="button" class="creator-report-btn" data-act="report"
                                        aria-label="Report this video" title="Report this video">
                                    Report
                                </button>
                            @endif
                        @endif
                    </div>
                    @if ($video->caption)
                        <p class="caption">{{ $video->caption }}</p>
                    @endif
                    <div class="tags">
                        <span class="tag">{{ $video->category->name }}</span>
                        @if ($video->is_ai_generated)
                            <span class="tag ai">AI-generated</span>
                        @endif
                    </div>
                </div>

                <div class="progress"><span></span></div>
            </section>
        @empty
            <div class="empty-feed">
                <div>
                    <h2>No reels here yet</h2>
                    <p>
                        @if ($mode === 'hide')
                            AI videos are hidden. Change the AI setting at the top, or upload your own video.
                        @elseif ($searchTerm !== '')
                            No videos matched your search. Try another word.
                        @elseif ($activeCategory)
                            Nothing in this category yet. Try another one, or upload a video.
                        @else
                            Be the first to share a video.
                        @endif
                    </p>
                    <a class="btn" href="{{ route('videos.create') }}">Upload a video</a>
                </div>
            </div>
        @endforelse
    </main>
@endsection

@section('sheets')
    <div class="sheet-backdrop" id="backdrop"></div>

    <div class="sheet" id="comments-sheet" role="dialog" aria-label="Comments">
        <div class="sheet-head">
            <strong>Comments</strong>
            <button type="button" class="icon-btn dark" data-close aria-label="Close">&times;</button>
        </div>
        <div class="sheet-body" id="comments-list"></div>
        <form class="sheet-form" id="comment-form" autocomplete="off">
            <input class="input" id="comment-input" maxlength="500" placeholder="Write a comment..." required>
            <button class="btn" type="submit">Post</button>
        </form>
    </div>

    <div class="sheet" id="more-sheet" role="dialog" aria-label="Options">
        <div class="sheet-head">
            <strong>Options</strong>
            <button type="button" class="icon-btn dark" data-close aria-label="Close">&times;</button>
        </div>
        <div class="sheet-body">
            <a class="sheet-item hide" id="more-edit" href="#">Edit this video</a>
            <button type="button" class="sheet-item danger hide" id="more-report">Report this video</button>
            <button type="button" class="sheet-item hide" id="more-mute"></button>
        </div>
    </div>

    <div class="sheet" id="report-sheet" role="dialog" aria-labelledby="report-title" aria-modal="true">
        <div class="sheet-head">
            <strong id="report-title">Why are you reporting this video?</strong>
            <button type="button" class="icon-btn dark" data-close aria-label="Close">&times;</button>
        </div>
        <form id="report-form">
            <div class="sheet-body report-options">
                <label><input type="radio" name="reason" value="unlabeled_ai" required> AI-generated content is not labeled</label>
                <label><input type="radio" name="reason" value="inappropriate"> Inappropriate or sensitive content</label>
                <label><input type="radio" name="reason" value="harassment"> Harassment or hateful behavior</label>
                <label><input type="radio" name="reason" value="spam"> Spam or misleading content</label>
                <label><input type="radio" name="reason" value="other"> Other concern</label>
            </div>
            <div class="sheet-form">
                <button type="button" class="btn ghost" data-close>Cancel</button>
                <button type="submit" class="btn danger" id="report-submit">Submit report</button>
            </div>
        </form>
    </div>
@endsection
