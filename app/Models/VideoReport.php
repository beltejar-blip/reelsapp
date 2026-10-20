<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoReport extends Model
{
    protected $fillable = ['video_id', 'user_id', 'reason'];
}
