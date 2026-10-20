<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Reels') - {{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="stylesheet" href="{{ asset('css/reels.css') }}?v={{ filemtime(public_path('css/reels.css')) }}">
</head>
<body>
<div class="app">
    <div class="content">
        @yield('content')
    </div>

    @yield('sheets')

    <div class="toast" id="toast" role="status"></div>

    <nav class="bottom">
        <a href="{{ route('reels.index') }}" class="{{ request()->routeIs('reels.index') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M10 8.5l6 3.5-6 3.5z"/></svg>
            Reels
        </a>
        <a href="{{ route('videos.create') }}" class="{{ request()->routeIs('videos.create') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 8v8M8 12h8"/></svg>
            Upload
        </a>
        <a href="{{ route('profile.index') }}" class="{{ request()->routeIs('profile.*', 'videos.index', 'videos.edit') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0114 0"/></svg>
            Profile
        </a>
    </nav>
</div>
<script src="{{ asset('js/reels.js') }}?v={{ filemtime(public_path('js/reels.js')) }}"></script>
</body>
</html>
