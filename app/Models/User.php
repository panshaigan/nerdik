<?php

namespace App\Models;

use App\Enums\NotificationPreferenceKey;
use Database\Factories\UserFactory;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
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
        'organization_id',
        'is_admin',
        'is_event_organizer',
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
    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_event_organizer' => 'boolean',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Canonical user identity. The single source of truth for how a user is rendered anywhere in the app.
     */
    public function displayName(): string
    {
        return (string) $this->nickname;
    }

    /**
     * Filament uses this to label the authenticated user in the admin UI (topbar, menus).
     */
    public function getFilamentName(): string
    {
        return $this->displayName();
    }

    /**
     * Build a unique nickname from an email's local-part, appending a numeric suffix on collision.
     */
    public static function generateUniqueNicknameFromEmail(string $email): string
    {
        $localPart = explode('@', $email)[0] ?? '';
        $base = Str::slug($localPart, '_');

        if ($base === '') {
            $base = 'user';
        }

        if (! self::where('nickname', $base)->exists()) {
            return $base;
        }

        $suffix = 2;
        while (self::where('nickname', $base.'_'.$suffix)->exists()) {
            $suffix++;
        }

        return $base.'_'.$suffix;
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
        return $this->morphedByMany(Event::class, 'interest', 'user_interests');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function interestedActivities()
    {
        return $this->morphedByMany(Activity::class, 'interest', 'user_interests');
    }

    /**
     * Resolved matrix: every known key is present; missing storage keys default both channels to true.
     *
     * @return array<string, array{in_app: bool, email: bool}>
     */
    public function resolvedNotificationPreferences(): array
    {
        $matrix = NotificationPreferenceKey::defaultMatrix();
        $stored = $this->profile?->notification_preferences;
        if (! is_array($stored)) {
            return $matrix;
        }

        foreach (NotificationPreferenceKey::cases() as $case) {
            $key = $case->value;
            $block = $stored[$key] ?? null;
            if (! is_array($block)) {
                continue;
            }

            if (array_key_exists('in_app', $block)) {
                $matrix[$key]['in_app'] = (bool) $block['in_app'];
            }
            if (array_key_exists('email', $block)) {
                $matrix[$key]['email'] = (bool) $block['email'];
            }
        }

        return $matrix;
    }

    public function wantsNotificationChannel(NotificationPreferenceKey $key, string $channel): bool
    {
        if ($channel !== 'in_app' && $channel !== 'email') {
            throw new \InvalidArgumentException('Channel must be in_app or email.');
        }

        return $this->resolvedNotificationPreferences()[$key->value][$channel];
    }

    /**
     * @param  array<string, array{in_app: bool, email: bool}>  $preferences
     */
    public function setNotificationPreferencesPayload(array $preferences): void
    {
        $profile = $this->profile()->firstOrCreate();
        $profile->notification_preferences = $preferences;
        $profile->save();
        $this->setRelation('profile', $profile);
    }

    /**
     * @param  array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}  $item
     */
    public function retainsScheduledDigestItem(array $item): bool
    {
        $category = isset($item['category']) ? (string) $item['category'] : '';
        $key = NotificationPreferenceKey::tryFromScheduledCategory($category);
        if ($key === null) {
            return true;
        }

        return $this->wantsNotificationChannel($key, 'in_app')
            || $this->wantsNotificationChannel($key, 'email');
    }

    protected function getTimezoneAttribute(): ?string
    {
        return $this->profile?->timezone;
    }
}
