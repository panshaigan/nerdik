<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'nickname',
        'email',
        'password',
        'is_admin',
        'google_id',
        'avatar_path',
        'discord_handle',
        'current_location',
        'timezone',
        'languages',
        'notify_email_proposal_updates',
        'notify_email_waitlist_promoted',
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'languages' => 'array',
            'notify_email_proposal_updates' => 'boolean',
            'notify_email_waitlist_promoted' => 'boolean',
            'is_admin' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->is_admin === true;
        }

        return true;
    }

    public function wishlistEvents()
    {
        return $this->belongsToMany(Event::class, 'user_event_wishlist')->withTimestamps();
    }

    public function wishlistActivities()
    {
        return $this->belongsToMany(Activity::class, 'user_activity_wishlist')->withTimestamps();
    }
}
