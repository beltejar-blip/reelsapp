<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $session_token_hash
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property int $attempts
 * @property bool $remember
 */
class LoginEmailOtp extends Model
{
    protected $fillable = [
        'user_id',
        'session_token_hash',
        'code_hash',
        'expires_at',
        'attempts',
        'remember',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempts' => 'integer',
            'remember' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
