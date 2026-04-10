<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'is_event_organizer',
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
            'is_event_organizer' => 'boolean',
        ];
    }

    public function canCreateEvents(): bool
    {
        return $this->is_event_organizer === true || $this->is_admin === true;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->is_admin === true;
        }

        return true;
    }

    /**
     * Whether this user may edit or delete the model: admins always; otherwise the row's `created_by` must match.
     */
    public function canModifyEntity(Model $entity): bool
    {
        if ($this->is_admin === true) {
            return true;
        }

        $ownerId = $entity->getAttribute('created_by');

        return $ownerId !== null && (int) $ownerId === (int) $this->id;
    }

    public function interestedEvents()
    {
        return $this->belongsToMany(Event::class, 'user_event_interests');
    }

    public function interestedActivities()
    {
        return $this->belongsToMany(Activity::class, 'user_activity_interests');
    }
}
