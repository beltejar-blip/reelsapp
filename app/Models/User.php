<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $facebook_id
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function videos()
    {
        return $this->hasMany(Video::class);
    }

    public function preference()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function mutedCreators()
    {
        return $this->belongsToMany(User::class, 'muted_creators', 'user_id', 'muted_user_id');
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'creator_follows', 'follower_id', 'creator_id')
            ->withTimestamps();
    }

    public function favoriteVideos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'video_favorites')->withTimestamps();
    }

    public function likedVideos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'video_likes')
            ->withPivot('reaction')
            ->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'creator_follows', 'creator_id', 'follower_id')
            ->withTimestamps();
    }

    public function mutedCategories()
    {
        return $this->belongsToMany(Category::class, 'muted_categories');
    }
}
