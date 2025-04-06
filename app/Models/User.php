<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @mixin IdeHelperUser
 */
final class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Determine if we can do more signups.
     */
    public static function canSignUp(): bool
    {
        return self::query()->count() < config('auth.defaults.max_users');
    }

    /**
     * Get the watchlist items for the user.
     *
     * @return HasMany<Watchlist,$this>
     */
    public function watchlists(): HasMany
    {
        return $this->hasMany(Watchlist::class);
    }

    /**
     * Check if the media item, by the given ID and type, is in the user's watchlist.
     *
     * @param  class-string<VodStream>|class-string<Series>  $mediaType
     */
    public function inMyWatchlist(int $mediaId, string $mediaType): bool
    {
        return $this->watchlists()
            ->where('watchable_type', $mediaType)
            ->where('watchable_id', $mediaId)
            ->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }
}
