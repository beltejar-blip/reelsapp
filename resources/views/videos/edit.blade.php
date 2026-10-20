@extends('reels.layout')

@section('title', 'Edit video')

@section('content')
    <div class="page">
        <div class="page-head">
            <h1 class="page-title">Edit video</h1>
            <a class="btn ghost small" href="{{ route('videos.index') }}">Back</a>
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

        <form method="POST" action="{{ route('videos.update', $video) }}" class="card">
            @csrf
            @method('PUT')

            <video class="preview" style="margin-top:0;margin-bottom:14px" src="{{ $video->url }}" controls preload="metadata" playsinline></video>

            <div class="field">
                <label for="category_id">Category</label>
                <select id="category_id" class="input" name="category_id" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" data-category-slug="{{ $category->slug }}" @selected(old('category_id', $video->category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="caption">Caption</label>
                <input id="caption" class="input" type="text" name="caption" value="{{ old('caption', $video->caption) }}" maxlength="255">
                <div class="counter" id="caption-count">0 / 255</div>
            </div>

            <div class="field">
                @php $lockedAi = $video->ai_label_source === 'reported'; @endphp
                <label class="switch">
                    <input type="checkbox" name="is_ai_generated" value="1"
                           @checked(old('is_ai_generated', $video->is_ai_generated)) @disabled($lockedAi)>
                    <span class="slider"></span>
                    <span>This video is AI-generated</span>
                </label>
                @if ($lockedAi)
                    <p class="muted small" style="margin-top:6px">This label was added after viewers reported the video, so it cannot be removed.</p>
                @else
                    <p class="muted small" style="margin-top:6px">AI-generated videos are automatically placed in the AI category.</p>
                @endif
            </div>

            <button type="submit" class="btn block">Save changes</button>
        </form>

        <form method="POST" action="{{ route('videos.destroy', $video) }}" class="card"
              onsubmit="return confirm('Delete this video? This cannot be undone.')">
            @csrf
            @method('DELETE')
            <p class="muted small" style="margin-bottom:10px">Deleting removes the video file, its likes and its comments.</p>
            <button type="submit" class="btn danger block">Delete video</button>
        </form>
    </div>
@endsection
