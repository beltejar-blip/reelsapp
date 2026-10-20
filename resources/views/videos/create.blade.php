@extends('reels.layout')

@section('title', 'Upload video')

@section('content')
    <div class="page">
        <div class="page-head">
            <h1 class="page-title">Upload a video</h1>
        </div>

        @if ($errors->any())
            <div class="alert err">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('videos.store') }}" enctype="multipart/form-data" class="card">
            @csrf

            <div class="field">
                <label for="video-input">Video file (mp4, webm, mov) - up to {{ $maxUploadLabel }}</label>
                <input id="video-input" class="input" type="file" name="video" required
                       accept="video/mp4,video/webm,video/quicktime" data-max="{{ $maxUploadBytes }}">
                <p id="size-warning" class="alert err hide" style="margin-top:10px">
                    That file is bigger than the allowed size. Pick a smaller video, or raise
                    upload_max_filesize and post_max_size in php.ini and restart the server.
                </p>
                <video id="video-preview" class="preview hide" controls muted playsinline></video>
            </div>

            <div class="field">
                <label for="category_id">Category</label>
                <select id="category_id" class="input" name="category_id" required>
                    <option value="">Choose a category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" data-category-slug="{{ $category->slug }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="caption">Caption</label>
                <input id="caption" class="input" type="text" name="caption" value="{{ old('caption') }}" maxlength="255" placeholder="Say something about your video">
                <div class="counter" id="caption-count">0 / 255</div>
            </div>

            <div class="field">
                <label class="switch">
                    <input type="checkbox" name="is_ai_generated" value="1" @checked(old('is_ai_generated'))>
                    <span class="slider"></span>
                    <span>This video is AI-generated</span>
                </label>
                <p class="muted small" style="margin-top:6px">AI-generated videos are automatically placed in the AI category.</p>
            </div>

            <button type="submit" class="btn block">Upload</button>
        </form>
    </div>
@endsection
