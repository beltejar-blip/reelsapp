<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Video extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'caption',
        'path',
        'thumbnail_path',
        'is_ai_generated',
        'ai_label_source',
    ];

    protected $casts = [
        'is_ai_generated' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function reports()
    {
        return $this->hasMany(VideoReport::class);
    }

    public function likes()
    {
        return $this->hasMany(VideoLike::class);
    }

    public function comments()
    {
        return $this->hasMany(VideoComment::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'video_favorites')->withTimestamps();
    }

    /** Public URL of the file; uses the current host so it works on any local port. */
    public function getUrlAttribute(): string
    {
        return asset('storage/'.$this->path);
    }
}
