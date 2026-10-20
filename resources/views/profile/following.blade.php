@extends('reels.layout')

@section('title', 'Following')

@section('content')
    <div class="page">
        <div class="page-head">
            <h1 class="page-title">Following</h1>
            <a class="btn ghost small" href="{{ route('profile.index') }}">Profile</a>
        </div>

        @if ($following->isEmpty())
            <div class="empty">
                <p>You are not following anyone yet.</p>
                <a class="btn" href="{{ route('reels.index') }}">Explore videos</a>
            </div>
        @else
            <div class="following-list">
                @foreach ($following as $creator)
                    <div class="following-person">
                        <span class="profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($creator->name, 0, 1)) }}</span>
                        <strong>{{ $creator->name }}</strong>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
